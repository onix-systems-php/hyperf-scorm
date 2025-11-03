<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfScorm\DTO\ScoDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormManifestDTO;
use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;
use OnixSystemsPHP\HyperfScorm\Exception\ScormParsingException;

class ScormManifestParser
{
    public function parse(string $manifestPath): ScormManifestDTO
    {
        if (!file_exists($manifestPath)) {
            throw new ScormParsingException("Manifest file not found: {$manifestPath}");
        }

        $xml = $this->loadXmlSafely($manifestPath);
        $this->validateRequiredElements($xml);
        $version = $this->detectScormVersion($xml);

        return  ScormManifestDTO::make([
            'title' => $this->getManifestTitle($xml),
            'version' => $version,
            'scos' => $this->parseScos($xml),
            'description' => $this->extractDescription($xml),
        ]);
    }

    private function loadXmlSafely(string $manifestPath): \SimpleXMLElement
    {
        libxml_use_internal_errors(true);

        $xml = simplexml_load_file($manifestPath);

        if ($xml === false) {
            $errors = libxml_get_errors();
            $errorMessage = 'XML parsing errors: ' . implode(
                ', ',
                array_map(static fn(object $error): string => trim($error->message), $errors)
            );
            libxml_clear_errors();
            throw new ScormParsingException($errorMessage);
        }

        libxml_clear_errors();
        return $xml;
    }

    private function validateRequiredElements(\SimpleXMLElement $xml): void
    {
        if (!isset($xml->metadata->schema)) {
            throw new ScormParsingException('Required element <schema> is missing from manifest metadata');
        }

        $schema = trim((string)$xml->metadata->schema);
        if (empty($schema)) {
            throw new ScormParsingException('Required element <schema> is empty in manifest metadata');
        }

        if ($schema !== 'ADL SCORM') {
            throw new ScormParsingException("Invalid schema value '{$schema}'. Expected 'ADL SCORM'");
        }

        $schemaVersion = $this->getSchemaVersion($xml);
        if (empty($schemaVersion)) {
            throw new ScormParsingException(
                'Required element <schemaversion> is missing or empty in manifest metadata'
            );
        }
    }

    private function detectScormVersion(\SimpleXMLElement $xml): string
    {
        $schemaVersion = $this->getSchemaVersion($xml);

        if (empty($schemaVersion)) {
            throw new ScormParsingException('Required schemaversion element is missing or empty');
        }

        $schemaLower = strtolower(trim($schemaVersion));

        if (str_contains($schemaLower, '2004') || $schemaLower === 'cam 1.3') {
            return ScormVersionEnum::SCORM_2004->value;
        }

        if ($schemaLower === 'cam 1.2' || str_contains($schemaLower, '1.2')) {
            return ScormVersionEnum::SCORM_12->value;
        }

        throw new ScormParsingException(
            "Unknown SCORM version in schemaversion: '{$schemaVersion}'. " .
            "Expected: '2004 3rd Edition', 'CAM 1.3' (SCORM 2004), 'CAM 1.2' or '1.2' (SCORM 1.2)"
        );
    }

    private function getSchemaVersion(\SimpleXMLElement $xml): ?string
    {
        if (isset($xml->metadata->schemaversion)) {
            return trim((string)$xml->metadata->schemaversion);
        }

        return null;
    }

    private function getManifestIdentifier(\SimpleXMLElement $xml): string
    {
        $identifier = (string)$xml['identifier'] ?? '';

        if (empty($identifier)) {
            throw new ScormParsingException('Manifest identifier not found');
        }

        return $identifier;
    }

    private function getManifestTitle(\SimpleXMLElement $xml): string
    {
        if (isset($xml->organizations->organization->title)) {
            return (string)$xml->organizations->organization->title;
        }

        if (isset($xml->organizations->organization['title'])) {
            return (string)$xml->organizations->organization['title'];
        }

        if (isset($xml->metadata->title)) {
            return (string)$xml->metadata->title;
        }

        return 'Untitled SCORM Package';
    }

    private function parseMetadata(\SimpleXMLElement $xml, ScormVersionEnum $version): array
    {
        $metadata = [];

        // Try to parse LOM metadata if present
        if (isset($xml->metadata->lom)) {
            $metadata['lom'] = $this->parseLOMMetadata($xml->metadata->lom);
        }

        // Parse version-specific metadata
        if ($version === ScormVersionEnum::SCORM_2004) {
            $metadata['scorm2004'] = $this->parseScorm2004Metadata($xml);
        } else {
            $metadata['scorm12'] = $this->parseScorm12Metadata($xml);
        }

        return $metadata;
    }


    private function parseLOMMetadata(\SimpleXMLElement $lom): array
    {
        $metadata = [];

        if (isset($lom->general)) {
            $metadata['general'] = [
                'title' => isset($lom->general->title->string) ? (string)$lom->general->title->string : null,
                'description' => isset($lom->general->description->string) ? (string)$lom->general->description->string : null,
                'keywords' => [],
                'language' => isset($lom->general->language) ? (string)$lom->general->language : null,
            ];

            if (isset($lom->general->keyword)) {
                foreach ($lom->general->keyword as $keyword) {
                    if (isset($keyword->string)) {
                        $metadata['general']['keywords'][] = (string)$keyword->string;
                    }
                }
            }
        }

        return $metadata;
    }

    private function parseScorm2004Metadata(\SimpleXMLElement $xml): array
    {
        $metadata = [];

        // Parse sequencing information if present
        if (isset($xml->organizations->organization->{'adlseq:sequencing'})) {
            $sequencing = $xml->organizations->organization->{'adlseq:sequencing'};
            $metadata['sequencing'] = [
                'choice' => (string)$sequencing['choice'] === 'true',
                'choiceExit' => (string)$sequencing['choiceExit'] === 'true',
                'flow' => (string)$sequencing['flow'] === 'true',
                'forwardOnly' => (string)$sequencing['forwardOnly'] === 'true',
            ];
        }

        return $metadata;
    }

    private function parseScorm12Metadata(\SimpleXMLElement $xml): array
    {
        return [
            'version' => '1.2',
            'parsed_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function parseScos(\SimpleXMLElement $xml): array
    {
        $scos = [];
        $resourcesMap = $this->createResourcesMap($xml);

        if (isset($xml->organizations->organization)) {
            foreach ($xml->organizations->organization as $org) {
                $scos = array_merge($scos, $this->extractScosFromItems($org, $resourcesMap));
            }
        }

        return $scos;
    }

    private function createResourcesMap(\SimpleXMLElement $xml): array
    {
        $resourcesMap = [];

        if (isset($xml->resources->resource)) {
            foreach ($xml->resources->resource as $resource) {
                $identifier = (string)$resource['identifier'];
                $resourcesMap[$identifier] = [
                    'href' => (string)$resource['href'],
                    'type' => (string)$resource['type'],
                    'scorm_type' => (string)($resource['adlcp:scormtype'] ?? ''),
                    'base' => (string)($resource['xml:base'] ?? ''),
                ];
            }
        }

        return $resourcesMap;
    }

    private function extractScosFromItems(\SimpleXMLElement $parent, array $resourcesMap): array
    {
        $scos = [];

        if (isset($parent->item)) {
            foreach ($parent->item as $item) {
                $identifierref = (string)($item['identifierref'] ?? '');

                if (!empty($identifierref) && isset($resourcesMap[$identifierref])) {
                    $resource = $resourcesMap[$identifierref];

                    $scos[] = ScoDTO::make([
                        'identifier' =>  $identifierref,
                        'title' =>  (string)($item->title ?? 'Untitled SCO'),
                        'launch_url' =>  $this->buildLaunchUrl($resource, $item),
                        'mastery_score' =>  isset($item['adlcp:masteryscore'])
                            ? (float)$item['adlcp:masteryscore']
                            : null,
                    ]);
                }

                if (isset($item->item)) {
                    $scos = array_merge($scos, $this->extractScosFromItems($item, $resourcesMap));
                }
            }
        }

        return $scos;
    }

    private function extractDescription(\SimpleXMLElement $xml): ?string
    {
        if (!isset($xml->metadata->lom->general->description->string)) {
            return null;
        }

        return (string) $xml->metadata->lom->general->description->string;
    }

    /**
     * Build complete launch URL from resource and item data
     */
    private function buildLaunchUrl(array $resource, \SimpleXMLElement $item): string
    {
        $href = $resource['href'];
        $base = $resource['base'];
        $parameters = (string)($item['parameters'] ?? '');

        $launchUrl = $base ? rtrim($base, '/') . '/' . ltrim($href, '/') : $href;

        if (!empty($parameters)) {
            $separator = strpos($launchUrl, '?') !== false ? '&' : '?';
            $launchUrl .= $separator . $parameters;
        }

        return $launchUrl;
    }
}
