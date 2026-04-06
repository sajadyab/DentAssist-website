<?php
declare(strict_types=1);

class UserRepository
{
    /**
     * @return array<int,array{id:mixed,full_name:mixed}>
     */
    public static function listDoctors(bool $onlyActive = false): array
    {
        $db = Database::getInstance();

        $sql = "SELECT id, full_name FROM users WHERE role = 'doctor'";
        if ($onlyActive) {
            $sql .= ' AND COALESCE(is_active, 1) = 1';
        }
        $sql .= ' ORDER BY full_name';

        return $db->fetchAll($sql);
    }

    public static function isDoctor(int $userId, bool $onlyActive = false): bool
    {
        $db = Database::getInstance();

        $sql = "SELECT id FROM users WHERE id = ? AND role = 'doctor'";
        if ($onlyActive) {
            $sql .= ' AND COALESCE(is_active, 1) = 1';
        }

        return (bool) $db->fetchOne($sql, [$userId], 'i');
    }
}

