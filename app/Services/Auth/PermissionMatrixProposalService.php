<?php

namespace App\Services\Auth;

use App\Models\ApplicationSetting;

final class PermissionMatrixProposalService
{
    private const SETTINGS_KEY = 'permissions.role_matrix_proposed';

    /**
     * @return array<string, list<string>>
     */
    public function baseMatrix(): array
    {
        /** @var array<string, list<string>> $matrix */
        $matrix = config('permissions.role_matrix', []);

        return $matrix;
    }

    /**
     * @return array<string, list<string>>
     */
    public function proposedMatrix(): array
    {
        $raw = ApplicationSetting::getValue(self::SETTINGS_KEY);
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $role => $permissions) {
            if (! is_string($role) || ! is_array($permissions)) {
                continue;
            }
            $out[$role] = $this->normalizePermissions($permissions);
        }

        return $out;
    }

    /**
     * @return array<string, list<string>>
     */
    public function draftMatrix(): array
    {
        $draft = $this->baseMatrix();
        foreach ($this->proposedMatrix() as $role => $permissions) {
            $draft[$role] = $permissions;
        }

        return $draft;
    }

    /**
     * @return list<string>
     */
    public function allKnownPermissions(): array
    {
        $set = [];
        foreach ($this->draftMatrix() as $permissions) {
            foreach ($permissions as $permission) {
                $set[$permission] = true;
            }
        }

        $all = array_keys($set);
        sort($all);

        return $all;
    }

    /**
     * @param  list<string>  $permissions
     */
    public function saveRoleProposal(string $role, array $permissions): void
    {
        $proposed = $this->proposedMatrix();
        $proposed[$role] = $this->normalizePermissions($permissions);

        ApplicationSetting::setValue(self::SETTINGS_KEY, json_encode($proposed, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    public function exportConfigString(): string
    {
        $matrix = $this->draftMatrix();
        $body = var_export(['role_matrix' => $matrix], true);

        return "<?php\n\nreturn ".$body.";\n";
    }

    /**
     * @param  array<int, mixed>  $permissions
     * @return list<string>
     */
    private function normalizePermissions(array $permissions): array
    {
        $clean = [];
        foreach ($permissions as $permission) {
            if (! is_string($permission)) {
                continue;
            }

            $v = trim($permission);
            if ($v === '') {
                continue;
            }
            $clean[] = $v;
        }

        $clean = array_values(array_unique($clean));
        sort($clean);

        return $clean;
    }
}
