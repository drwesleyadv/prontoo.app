<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

final class TaskQuerySql
{
    private const ACTIVE = "t.status IN ('aberta','em_andamento','aguardando')";

    private function __construct()
    {
    }

    public static function taskList(string $view, bool $search): string
    {
        $where = 't.clinic_id=?';
        if (!$search) {
            $where .= match ($view) {
                'mine' => ' AND ' . self::ACTIVE . ' AND t.assigned_to=?',
                'role' => " AND " . self::ACTIVE . " AND t.assigned_to IS NULL AND COALESCE(t.target_scope,'clinic')='role' AND t.target_role=?",
                'clinic' => " AND " . self::ACTIVE . " AND t.assigned_to IS NULL AND COALESCE(t.target_scope,'clinic')='clinic'",
                'progress' => " AND t.status='em_andamento'",
                'overdue' => ' AND ' . self::ACTIVE . ' AND t.due_at IS NOT NULL AND t.due_at<NOW()',
                'today' => ' AND ' . self::ACTIVE . ' AND t.due_at IS NOT NULL AND t.due_at>=? AND t.due_at<?',
                'done' => " AND t.status NOT IN ('aberta','em_andamento','aguardando')",
                'all' => '',
                default => ' AND ' . self::ACTIVE . ' AND t.assigned_to=?',
            };
        }
        if ($search) {
            $where .= " AND (t.title LIKE ? OR td.description LIKE ? OR p.full_name LIKE ? OR u.name LIKE ? OR DATE_FORMAT(t.due_at,'%d/%m/%Y') LIKE ? OR DATE_FORMAT(t.created_at,'%d/%m/%Y') LIKE ?)";
        }
        return $where;
    }

    public static function active(): string
    {
        return self::ACTIVE;
    }

    public static function floatingAccess(bool $clinicWide): string
    {
        $scope = "COALESCE(NULLIF(t.target_scope,''),CASE WHEN t.target_user_id IS NOT NULL THEN 'user' WHEN NULLIF(t.target_role,'') IS NOT NULL THEN 'role' ELSE 'clinic' END)";
        $clinic = $clinicWide ? " OR {$scope}='clinic'" : '';
        return "(t.assigned_to=? OR (t.assigned_to IS NULL AND (({$scope}='role' AND t.target_role=?) OR ({$scope}='user' AND t.target_user_id=?){$clinic})))";
    }
}
