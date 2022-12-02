<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;


use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\ContactForm;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;
use Generator;
use PDOException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class ContactFormDataStore
{
    /**
     * Declare Database Service
     *
     * @var DatabaseService|mixed
     */
    protected $dbs;

    /**
     * @var mixed|LoggerInterface
     */
    protected $logger;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * UserDataStore constructor.
     *
     * @param DatabaseService $dbs
     * @param LoggerInterface $logger
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(DatabaseService $dbs, LoggerInterface $logger, CacheItemPoolInterface $cache)
    {
        $this->dbs = $dbs;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * @param int $formId
     * @return ContactForm|null
     * @throws DatabaseException
     */
    public function findById(int $formId): ?ContactForm
    {
        try {
            $sql = <<<SQL
                SELECT * 
                FROM contact_forms 
                WHERE id = :id;
SQL;

            $record = $this->dbs->selectOne($sql, ['id' => $formId]);
            return empty($record) ? null : ContactForm::withDBData($record);
        }
        catch(PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $ownerId
     * @return Generator
     * @throws DatabaseException
     */
    public function findForUser(int $ownerId): Generator
    {
        try {
            $sql = <<<SQL
                SELECT * 
                FROM contact_forms 
                WHERE owner_id = :owner_id;
SQL;

            foreach ($this->dbs->cursor($sql, ['owner_id' => $ownerId]) as $row) {
                yield ContactForm::withDBData($row);
            }
            return null;
        }
        catch(PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param ContactForm $contactForm
     * @return ContactForm|null
     * @throws DatabaseException
     */
    public function create(ContactForm $contactForm): ?ContactForm
    {
        try {
            $sql = <<<SQL
                INSERT INTO contact_forms(owner_id, form_title, confirmation_message, form_hash, copy_from_channel_id, enable_overlay, color, enabled)
                VALUES (:owner_id, :form_title, :confirmation_message, :form_hash, :copy_from_channel_id, :enable_overlay, :color, :enabled);
SQL;

            $contactForm->setFormHash($this->generateUniqueHash());

            $data = array_diff_key($contactForm->getArray(), array_flip(['created_on', 'updated_on']));
            if (!$this->dbs->insert($sql, $data)) {
                return null;
            }
            $contactForm->setId($this->dbs->lastInsertId());
            return $contactForm;
        }
        catch(PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function update(ContactForm $contactForm): ?ContactForm
    {
        try {
            $sql = <<<SQL
                UPDATE contact_forms
                SET 
                    owner_id = :owner_id, 
                    form_title = :form_title, 
                    confirmation_message = :confirmation_message, 
                    form_hash = :form_hash,
                    copy_from_channel_id = :copy_from_channel_id,
                    enable_overlay = :enable_overlay,
                    color = :color,
                    enabled = :enabled
                WHERE id = :id
SQL;

            $data = array_diff_key($contactForm->getArray(), array_flip(['created_on', 'updated_on']));
            if (!$this->dbs->update($sql, $data)) {
                return null;
            }
            return $contactForm;
        }
        catch(PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function delete(int $contactFormId): bool
    {
        try {
            $sql = <<<SQL
                DELETE FROM contact_forms
                WHERE id = :id
SQL;
            if (!$this->dbs->delete($sql, ['id' => $contactFormId])) {
                return false;
            }
            return true;
        }
        catch(PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function generateUniqueHash(): string
    {
        $sql = "SELECT 1 FROM contact_forms WHERE form_hash = :hash";
        do {
            $hash = StringUtility::generateRandomString(64);
        } while ($this->dbs->selectOne($sql, ['hash' =>  $hash]) !== null);

        return $hash;
    }

    public function findByHash(string $formHash): ?ContactForm
    {
        try {
            $sql = <<<SQL
                SELECT * 
                FROM contact_forms 
                WHERE form_hash = :form_hash;
SQL;

            $record = $this->dbs->selectOne($sql, ['form_hash' => $formHash]);
            return empty($record) ? null : ContactForm::withDBData($record);
        }
        catch(PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }


}