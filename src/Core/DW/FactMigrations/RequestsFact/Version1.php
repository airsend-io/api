<?php

namespace CodeLathe\Core\DW\FactMigrations\RequestsFact;

use CodeLathe\Core\DW\FactMigrations\AbstractMigration;

class Version1 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Add the version field and refactor language dimension.";
    }

    /**
     * @inheritDoc
     */
    public function handle(): bool
    {

        try {
            $params = [
                'index' => $this->indexName,
                'body' => [
                    '_source' => [
                        'enabled' => true
                    ],
                    'properties' => [
                    ]
                ]
            ];

            $currentMappings = $this->elasticClient->indices()->getMapping(['index' => $this->indexName]);

            // first verify if we already have a version field
            $currentVersionMapping = $currentMappings[$this->indexName]['mappings']['properties']['version'] ?? null;

            // add the version mapping if it doesn't exists
            if ($currentVersionMapping === null || ($currentVersionMapping['type'] ?? '') !== 'integer') {
                $params['body']['properties']['version'] = [
                    'type' => 'integer'
                ];
            }

            // then verify if there is a language.complete_lang field on the mappings
            if (!isset($currentMappings[$this->indexName]['mappings']['properties']['language']['properties']['complete_lang'])) {
                $params['body']['properties']['language']['properties']['complete_lang'] = [
                    'type' => 'keyword'
                ];
            }

            // finally update the index mappings
            $this->elasticClient->indices()->putMapping($params);

        } catch (\Throwable $e) {
            $this->logger->debug($e->getMessage());
            return false;
        }

        return true;
    }
}