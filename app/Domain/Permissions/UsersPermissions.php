<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/Runtime/Autoload/ProntooAutoloader.php';
function require_user_in_clinic(int $cid, int $uid, array $roles = []): void
{
    \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations01::require_user_in_clinic($cid, $uid, $roles);
}
function save_team_member(int $cid, array $data): ?int
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations01::save_team_member($cid, $data);
}
function clinic_user_exists(int $cid, int $uid, array $roles = []): bool
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_user_exists($cid, $uid, $roles);
}
function person_has_other_clinic_links(int $personId, int $cid): bool
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations01::person_has_other_clinic_links($personId, $cid);
}
function user_name_by_id(?int $uid): string
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations01::user_name_by_id($uid);
}
function single_active_role_cleanup_for_user(int $uid, ?int $cid = null): void
{
    \Prontoo\Domain\Legacy\UsersPermissions\UsersPermissionsDomainOperations01::single_active_role_cleanup_for_user($uid, $cid);
}
function clinic_minimum_roles_violation_message(): string
{
    return \Prontoo\Domain\Legacy\UsersPermissions\UsersPermissionsDomainOperations01::clinic_minimum_roles_violation_message();
}
function clinic_active_role_count(
    int $cid,
    string $role,
    ?int $excludingUserId = null,
): int {
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_active_role_count($cid, $role, $excludingUserId);
}
function clinic_assert_minimum_roles_after_change(
    int $cid,
    int $targetUserId,
    array $targetRoles,
): void {
    \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_assert_minimum_roles_after_change($cid, $targetUserId, $targetRoles);
}
function clinic_assert_can_deactivate_user_roles(int $cid, int $uid): void
{
    \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_assert_can_deactivate_user_roles($cid, $uid);
}
function clinic_auto_assign_missing_managers(int $limit = 200): int
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_auto_assign_missing_managers($limit);
}
function active_clinic_roles_for_user(int $uid): array
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations01::active_clinic_roles_for_user($uid);
}
function user_is_global_admin(int $uid): bool
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations01::user_is_global_admin($uid);
}
function manageable_team_roles(?int $clinicId = null): array
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations01::manageable_team_roles($clinicId);
}
function can_manage_team_role(string $role): bool
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations01::can_manage_team_role($role);
}
function selected_team_roles(array $data, ?int $cid = null): array
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations01::selected_team_roles($data, $cid);
}
function clinic_restore_default_permissions_for_role(
    int $cid,
    string $role,
): void {
    \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_restore_default_permissions_for_role($cid, $role);
}
function clinic_enable_roles_for_assignment(
    int $cid,
    array $roles,
    string $reason = "assignment",
): void {
    \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_enable_roles_for_assignment($cid, $roles, $reason);
}
function clinic_enable_roles_from_active_user_links(int $uid): void
{
    \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations02::clinic_enable_roles_from_active_user_links($uid);
}
function sync_user_roles_for_clinic(
    int $cid,
    int $uid,
    array $roles,
    bool $propagate = true,
): array {
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations02::sync_user_roles_for_clinic($cid, $uid, $roles, $propagate);
}
function active_role_codes_for_user_in_clinic(int $cid, int $uid): array
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations02::active_role_codes_for_user_in_clinic($cid, $uid);
}
function preferred_active_role_link_for_user(
    int $cid,
    int $uid,
    ?int $preferRoleId = null,
): ?array {
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations02::preferred_active_role_link_for_user($cid, $uid, $preferRoleId);
}
function propagate_user_role_permissions(
    int $cid,
    int $uid,
    string $reason = "roles_updated",
): void {
    \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations02::propagate_user_role_permissions($cid, $uid, $reason);
}
function role_labels_from_codes(array $codes, ?int $cid = null): array
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations02::role_labels_from_codes($codes, $cid);
}
function role_badges_html(array $codes, ?int $cid = null): string
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations02::role_badges_html($codes, $cid);
}
function role_checkbox_group(
    string $name,
    array $options,
    array $selected,
): string {
    return \Prontoo\Presentation\Legacy\UsersPermissions\UsersPermissionsPresentationOperations01::role_checkbox_group($name, $options, $selected);
}
function team_options(int $cid): array
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations02::team_options($cid);
}
function clinic_role_user_ids(int $cid, array $roles): array
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations02::clinic_role_user_ids($cid, $roles);
}
function permission_operations(): array
{
    return \Prontoo\Domain\Legacy\UsersPermissions\UsersPermissionsDomainOperations01::permission_operations();
}
function permission_module_defs(): array
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_module_defs();
}
function permission_operation_supported(string $module, string $op): bool
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_operation_supported($module, $op);
}
function permission_module_available_for_role(
    string $role,
    string $module,
): bool {
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_module_available_for_role($role, $module);
}
function permission_operation_configurable(
    string $role,
    string $module,
    string $op,
): bool {
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_operation_configurable($role, $module, $op);
}
function permission_default_ops_for_role(string $role, string $module): array
{
    return \Prontoo\Domain\Legacy\UsersPermissions\UsersPermissionsDomainOperations01::permission_default_ops_for_role($role, $module);
}
function permission_default_for(
    string $role,
    string $module,
    string $op,
    int $cid,
): bool {
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_default_for($role, $module, $op, $cid);
}
function permission_rules_for_role(string $role, int $cid): array
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations02::permission_rules_for_role($role, $cid);
}
function persist_permission_rules(int $cid, string $role, array $posted): void
{
    \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations02::persist_permission_rules($cid, $role, $posted);
}
function collaborator_permission_matrix_base(string $role, int $cid): array
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations03::collaborator_permission_matrix_base($role, $cid);
}
function collaborator_permission_matrix(string $role, int $cid): array
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations03::collaborator_permission_matrix($role, $cid);
}
function collaborator_permission_table(string $role, int $cid): string
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations03::collaborator_permission_table($role, $cid);
}
function collaborator_permission_summary(array $roles, int $cid): string
{
    return \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations03::collaborator_permission_summary($roles, $cid);
}
function page_users(): void
{
    \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations04::page_users();
}
function page_user(): void
{
    \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations05::page_user();
}
function page_permissions(): void
{
    \Prontoo\Runtime\Legacy\UsersPermissions\UsersPermissionsRuntimeOperations05::page_permissions();
}
