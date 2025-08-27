<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;
use OnixSystemsPHP\HyperfScorm\DTO\ScormManifestDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScoDTO;
use OnixSystemsPHP\HyperfScorm\Exception\ScormParsingException;

/**
 * Simplified SCORM Manifest Parser using SimpleXML
 * Much cleaner and easier to maintain than DOMDocument approach
 */
class ScormManifestParserSimple
{
    /**
     * Parse SCORM manifest XML file and extract metadata
     */
    public function parse(string $manifestPath): ScormManifestDTO
    {
        if (!file_exists($manifestPath)) {
            throw new ScormParsingException("Manifest file not found: {$manifestPath}");
        }

        $xml = $this->loadXmlSafely($manifestPath);
        $version = $this->detectScormVersion($xml);

        // Создаем scos из resources (resource = SCO в SCORM)
        $scos = $this->createScosFromResources($xml);


        return ScormManifestDTO::make([
            'title' => $this->getManifestTitle($xml),
            'version' => $version,
            'scos' => $scos,
            'description' => $this->extractDescription($xml),
        ]);
    }

    /**
     * Safely load XML with SimpleXML and error handling
     */
    private function loadXmlSafely(string $manifestPath): \SimpleXMLElement
    {
        libxml_use_internal_errors(true);

        $xml = simplexml_load_file($manifestPath);

        if ($xml === false) {
            $errors = libxml_get_errors();
            $errorMessage = 'XML parsing errors: ' . implode(', ', array_map(fn($error) => trim($error->message), $errors));
            libxml_clear_errors();
            throw new ScormParsingException($errorMessage);
        }

        libxml_clear_errors();
        return $xml;
    }

    /**
     * Detect SCORM version from manifest XML using SimpleXML
     */
    private function detectScormVersion(\SimpleXMLElement $xml): ScormVersionEnum
    {
        // PRIMARY: Check schemaversion element in metadata (most reliable)
        $schemaVersion = null;

        // Try direct metadata access
        if (isset($xml->metadata->schemaversion)) {
            $schemaVersion = (string) $xml->metadata->schemaversion;
        }
        // Try organizations metadata
        elseif (isset($xml->organizations->organization->metadata->schemaversion)) {
            $schemaVersion = (string) $xml->organizations->organization->metadata->schemaversion;
        }

        if ($schemaVersion) {
            $schemaVersion = trim($schemaVersion);

            // SCORM 2004 versions
            if (str_contains(strtolower($schemaVersion), '2004')) {
                return ScormVersionEnum::SCORM_2004->value;
            }

            // SCORM 1.2 versions (CAM 1.3 is SCORM 1.2)
            if (str_contains(strtolower($schemaVersion), 'cam') || $schemaVersion === '1.2') {
                return ScormVersionEnum::SCORM_12->value;
            }
        }

        // SECONDARY: Check manifest version attribute
        $manifestVersion = (string) $xml['version'] ?? null;
        if ($manifestVersion) {
                    // SCORM 2004 manifest versions are typically higher (1.3+)
        if (version_compare($manifestVersion, '1.3', '>=')) {
            return ScormVersionEnum::SCORM_2004->value;
        }
        // SCORM 1.2 manifest versions are 1.0-1.2
        if (version_compare($manifestVersion, '1.3', '<')) {
            return ScormVersionEnum::SCORM_12->value;
        }
        }

        // TERTIARY: Check namespaces
        $namespaces = $xml->getNamespaces(true);

        // SCORM 2004 namespaces
        $scorm2004Namespaces = [
            'http://www.imsglobal.org/xsd/imscp_v1p1',
            'http://www.adlnet.org/xsd/adlcp_v1p3',
            'http://www.adlnet.org/xsd/adlseq_v1p3',
            'http://www.adlnet.org/xsd/adlnav_v1p3'
        ];

        foreach ($scorm2004Namespaces as $namespace) {
            if (in_array($namespace, $namespaces)) {
                return ScormVersionEnum::SCORM_2004->value;
            }
        }

        // SCORM 1.2 namespaces
        $scorm12Namespaces = [
            'http://www.imsproject.org/xsd/imscp_rootv1p1p2',
            'http://www.adlnet.org/xsd/adlcp_rootv1p2'
        ];

        foreach ($scorm12Namespaces as $namespace) {
            if (in_array($namespace, $namespaces)) {
                return ScormVersionEnum::SCORM_12;
            }
        }

        // Default to SCORM 1.2
        return ScormVersionEnum::SCORM_12;
    }

    /**
     * Get manifest identifier using SimpleXML
     */
    private function getManifestIdentifier(\SimpleXMLElement $xml): string
    {
        $identifier = (string) $xml['identifier'] ?? '';

        if (empty($identifier)) {
            throw new ScormParsingException('Manifest identifier not found');
        }

        return $identifier;
    }

    /**
     * Get manifest title using SimpleXML
     */
    private function getManifestTitle(\SimpleXMLElement $xml): string
    {
        // Try to get title from organizations
        if (isset($xml->organizations->organization->title)) {
            return (string) $xml->organizations->organization->title;
        }

        // Try to get title from organization attribute
        if (isset($xml->organizations->organization['title'])) {
            return (string) $xml->organizations->organization['title'];
        }

        // Try to get title from metadata
        if (isset($xml->metadata->title)) {
            return (string) $xml->metadata->title;
        }

        return 'Untitled SCORM Package';
    }

    /**
     * Parse organizations section using SimpleXML
     */
    private function parseOrganizations(\SimpleXMLElement $xml): array
    {
        $organizations = [];

        if (isset($xml->organizations->organization)) {
            foreach ($xml->organizations->organization as $org) {
                $organizations[] = [
                    'identifier' => (string) $org['identifier'] ?? '',
                    'title' => (string) $org->title ?? (string) $org['title'] ?? '',
                    'items' => $this->parseItems($org),
                ];
            }
        }

        return $organizations;
    }

    /**
     * Parse organization items using SimpleXML
     */
    private function parseItems(\SimpleXMLElement $organization): array
    {
        $items = [];

        if (isset($organization->item)) {
            foreach ($organization->item as $item) {
                $items[] = [
                    'identifier' => (string) $item['identifier'] ?? '',
                    'identifierref' => (string) $item['identifierref'] ?? '',
                    'title' => (string) $item->title ?? '',
                    'isvisible' => (string) $item['isvisible'] ?? 'true',
                ];
            }
        }

        return $items;
    }

    /**
     * Parse resources section using SimpleXML
     */
    private function parseResources(\SimpleXMLElement $xml): array
    {
        $resources = [];

        if (isset($xml->resources->resource)) {
            foreach ($xml->resources->resource as $resource) {
                $files = [];
                if (isset($resource->file)) {
                    foreach ($resource->file as $file) {
                        $files[] = (string) $file['href'] ?? '';
                    }
                }

                $resources[] = [
                    'identifier' => (string) $resource['identifier'] ?? '',
                    'type' => (string) $resource['type'] ?? '',
                    'href' => (string) $resource['href'] ?? '',
                    'scormType' => (string) $resource['adlcp:scormType'] ?? '',
                    'files' => $files,
                ];
            }
        }

        return $resources;
    }

    /**
     * Parse metadata section using SimpleXML
     */
    private function parseMetadata(\SimpleXMLElement $xml): array
    {
        $metadata = [];

        if (isset($xml->metadata)) {
            $metadata['schema'] = (string) $xml->metadata->schema ?? '';
            $metadata['schemaversion'] = (string) $xml->metadata->schemaversion ?? '';

            if (isset($xml->metadata->adlcp) && $xml->metadata->adlcp->location) {
                $metadata['location'] = (string) $xml->metadata->adlcp->location;
            }
        }

        return $metadata;
    }

    /**
     * Создать SCOs из resources (совместимо с SCORM 1.2 и 2004)
     */
    private function createScosFromResources(\SimpleXMLElement $xml): array
    {
        $scos = [];

        if (isset($xml->resources->resource)) {
            foreach ($xml->resources->resource as $resource) {
                $identifier = (string)($resource['identifier'] ?? '');
                $href = (string)($resource['href'] ?? '');
                $type = (string)($resource['type'] ?? 'webcontent');

                // Совместимость с обеими версиями SCORM (case-insensitive)
                $scormType = $this->getScormType($resource);

                // Получаем данные из соответствующего item в organizations
                $itemData = $this->getItemDataFromOrganizations($xml, $identifier);

                $scos[] = ScoDTO::make([
                    'identifier' =>  $identifier,
                    'title' =>  $itemData['title'] ?? 'Untitled SCO',
                    'launch_url' =>  $this->buildLaunchUrl($href, $itemData['parameters'] ?? ''),
                    'mastery_score' =>  $itemData['mastery_score'] ? (float)$itemData['mastery_score'] : null,
                ]);
            }
        }

        return $scos;
    }

    /**
     * Получить scormType с совместимостью для SCORM 1.2 и 2004
     */
    private function getScormType(\SimpleXMLElement $resource): string
    {
        // SCORM 2004: adlcp:scormType
        $scormType = (string)($resource['adlcp:scormType'] ?? '');
        if (!empty($scormType)) {
            return $scormType;
        }

        // SCORM 1.2: adlcp:scormtype (маленькими буквами)
        $scormType = (string)($resource['adlcp:scormtype'] ?? '');
        if (!empty($scormType)) {
            return $scormType;
        }

        // Если не указан, считаем что это SCO
        return 'sco';
    }

    /**
     * Получить данные из organizations по identifierref (совместимо с SCORM 1.2 и 2004)
     */
    private function getItemDataFromOrganizations(\SimpleXMLElement $xml, string $resourceId): array
    {
        if (isset($xml->organizations->organization)) {
            foreach ($xml->organizations->organization as $org) {
                if (isset($org->item)) {
                    foreach ($org->item as $item) {
                        $identifierref = (string)($item['identifierref'] ?? '');
                        if ($identifierref === $resourceId) {
                            return [
                                'title' => (string)($item->title ?? 'Untitled SCO'),
                                'parameters' => (string)($item['parameters'] ?? ''),
                                'prerequisites' => (string)($item['adlcp:prerequisites'] ?? ''),
                                'mastery_score' => (string)($item['adlcp:masteryscore'] ?? ''),
                                'max_time_allowed' => (string)($item['adlcp:maxtimeallowed'] ?? ''),
                                'time_limit_action' => (string)($item['adlcp:timelimitaction'] ?? ''),
                                'is_visible' => (string)($item['isvisible'] ?? 'true') === 'true',
                            ];
                        }
                    }
                }
            }
        }

        // Fallback если item не найден
        return [
            'title' => $resourceId ?: 'Untitled SCO',
            'parameters' => '',
            'prerequisites' => '',
            'mastery_score' => '',
            'max_time_allowed' => '',
            'time_limit_action' => '',
            'is_visible' => true,
        ];
    }

    private function extractDescription(\SimpleXMLElement $xml): ?string
    {
        if (isset($xml->metadata->lom->general->description->string)) {
            return (string) $xml->metadata->lom->general->description->string;
        }

        return null;
    }
    /**
     * Построить полный launch URL с параметрами
     */
    private function buildLaunchUrl(string $href, string $parameters): string
    {
        if (empty($parameters)) {
            return $href;
        }

        $separator = strpos($href, '?') !== false ? '&' : '?';
        return $href . $separator . $parameters;
    }
}
