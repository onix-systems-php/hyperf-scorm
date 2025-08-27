<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfScorm\DTO\PlayerManifestDTO;
use OnixSystemsPHP\HyperfScorm\DTO\PlayerScoDTO;

/**
 * Simplified SCORM manifest parser optimized for player needs
 * Parses only essential data required for SCORM playback
 */
class PlayerManifestParser
{
    /**
     * Parse SCORM manifest XML and return player-optimized DTO
     */
    public function parse(string $xmlContent): PlayerManifestDTO
    {
        $xml = new \SimpleXMLElement($xmlContent);
        
        // Register namespaces
        $xml->registerXPathNamespace('adlcp', 'http://www.adlnet.org/xsd/adlcp_rootv1p2');
        $xml->registerXPathNamespace('adlseq', 'http://www.adlnet.org/xsd/adlseq_rootv1p2');
        $xml->registerXPathNamespace('adlnav', 'http://www.adlnet.org/xsd/adlnav_rootv1p2');
        
        return new PlayerManifestDTO(
            title: $this->extractTitle($xml),
            version: $this->extractVersion($xml),
            scorm_version: $this->extractScormVersion($xml),
            scos: $this->createPlayerScos($xml),
            description: $this->extractDescription($xml),
        );
    }

    /**
     * Extract package title from manifest
     */
    private function extractTitle(\SimpleXMLElement $xml): string
    {
        // Try metadata path first
        if (isset($xml->metadata->lom->general->title->string)) {
            return (string) $xml->metadata->lom->general->title->string;
        }
        
        // Fallback to organizations title
        if (isset($xml->organizations->organization->title)) {
            return (string) $xml->organizations->organization->title;
        }
        
        return 'Untitled SCORM Package';
    }

    /**
     * Extract manifest version
     */
    private function extractVersion(\SimpleXMLElement $xml): string
    {
        return (string) ($xml['version'] ?? '1.3');
    }

    /**
     * Extract SCORM version from metadata
     */
    private function extractScormVersion(\SimpleXMLElement $xml): string
    {
        // Check metadata schema
        if (isset($xml->metadata->schema)) {
            $schema = (string) $xml->metadata->schema;
            if (str_contains($schema, '2004')) {
                return '2004';
            }
            if (str_contains($schema, '1.2')) {
                return '1.2';
            }
        }
        
        // Check for SCORM 2004 specific elements
        if (isset($xml->organizations->organization->sequencing)) {
            return '2004';
        }
        
        return '1.2'; // Default to SCORM 1.2
    }

    /**
     * Extract package description
     */
    private function extractDescription(\SimpleXMLElement $xml): ?string
    {
        if (isset($xml->metadata->lom->general->description->string)) {
            return (string) $xml->metadata->lom->general->description->string;
        }
        
        return null;
    }

    /**
     * Create simplified SCOs for player
     */
    private function createPlayerScos(\SimpleXMLElement $xml): array
    {
        $scos = [];
        
        if (isset($xml->resources->resource)) {
            foreach ($xml->resources->resource as $resource) {
                $identifier = (string) ($resource['identifier'] ?? '');
                $href = (string) ($resource['href'] ?? '');
                
                if (empty($identifier) || empty($href)) {
                    continue; // Skip invalid resources
                }
                
                // Get item data from organizations
                $itemData = $this->getItemDataFromOrganizations($xml, $identifier);
                
                // Parse time limit to seconds
                $maxTimeSeconds = $this->parseTimeToSeconds($itemData['max_time_allowed'] ?? '');
                
                $scos[] = new PlayerScoDTO(
                    identifier: $identifier,
                    title: $itemData['title'] ?? 'Untitled SCO',
                    launch_url: $this->buildLaunchUrl($href, $itemData['parameters'] ?? ''),
                    mastery_score: $itemData['mastery_score'] ? (float) $itemData['mastery_score'] : null,
                    max_time_seconds: $maxTimeSeconds,
                );
            }
        }
        
        return $scos;
    }

    /**
     * Get item data from organizations section
     */
    private function getItemDataFromOrganizations(\SimpleXMLElement $xml, string $resourceId): array
    {
        if (isset($xml->organizations->organization)) {
            foreach ($xml->organizations->organization as $org) {
                if (isset($org->item)) {
                    foreach ($org->item as $item) {
                        $identifierref = (string) ($item['identifierref'] ?? '');
                        if ($identifierref === $resourceId) {
                            return [
                                'title' => (string) ($item->title ?? 'Untitled SCO'),
                                'parameters' => (string) ($item['parameters'] ?? ''),
                                'mastery_score' => (string) ($item['adlcp:masteryscore'] ?? ''),
                                'max_time_allowed' => (string) ($item['adlcp:maxtimeallowed'] ?? ''),
                            ];
                        }
                    }
                }
            }
        }
        
        // Fallback data
        return [
            'title' => $resourceId ?: 'Untitled SCO',
            'parameters' => '',
            'mastery_score' => '',
            'max_time_allowed' => '',
        ];
    }

    /**
     * Build launch URL with parameters
     */
    private function buildLaunchUrl(string $href, string $parameters): string
    {
        if (empty($parameters)) {
            return $href;
        }
        
        $separator = strpos($href, '?') !== false ? '&' : '?';
        return $href . $separator . $parameters;
    }

    /**
     * Parse SCORM time format to seconds
     * Supports formats: "PT30M", "PT1H30M", "P1DT2H30M", "00:30:00"
     */
    private function parseTimeToSeconds(string $timeString): ?int
    {
        if (empty($timeString)) {
            return null;
        }
        
        // Handle ISO 8601 duration format (PT30M, PT1H30M, etc.)
        if (preg_match('/^PT(\d+H)?(\d+M)?(\d+S)?$/', $timeString, $matches)) {
            $hours = (int) (preg_replace('/[^0-9]/', '', $matches[1] ?? ''));
            $minutes = (int) (preg_replace('/[^0-9]/', '', $matches[2] ?? ''));
            $seconds = (int) (preg_replace('/[^0-9]/', '', $matches[3] ?? ''));
            
            return $hours * 3600 + $minutes * 60 + $seconds;
        }
        
        // Handle HH:MM:SS format
        if (preg_match('/^(\d+):(\d+):(\d+)$/', $timeString, $matches)) {
            $hours = (int) $matches[1];
            $minutes = (int) $matches[2];
            $seconds = (int) $matches[3];
            
            return $hours * 3600 + $minutes * 60 + $seconds;
        }
        
        // Handle MM:SS format
        if (preg_match('/^(\d+):(\d+)$/', $timeString, $matches)) {
            $minutes = (int) $matches[1];
            $seconds = (int) $matches[2];
            
            return $minutes * 60 + $seconds;
        }
        
        // Try to parse as plain seconds
        if (is_numeric($timeString)) {
            return (int) $timeString;
        }
        
        return null;
    }
}

