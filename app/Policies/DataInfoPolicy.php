<?php

namespace App\Policies;

use App\Authorization\Permissions;
use App\Models\DataInfo;
use App\Models\User;

/**
 * InfoClube: ler é de todo mundo logado; criar, editar e excluir é da
 * permissão `infoclube.editar` (Secretaria na matriz inicial).
 */
class DataInfoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DataInfo $dataInfo): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::INFOCLUBE_EDITAR);
    }

    public function update(User $user, DataInfo $dataInfo): bool
    {
        return $user->can(Permissions::INFOCLUBE_EDITAR);
    }

    public function delete(User $user, DataInfo $dataInfo): bool
    {
        return $user->can(Permissions::INFOCLUBE_EDITAR);
    }

    public function restore(User $user, DataInfo $dataInfo): bool
    {
        return $user->can(Permissions::INFOCLUBE_EDITAR);
    }

    public function forceDelete(User $user, DataInfo $dataInfo): bool
    {
        return $user->can(Permissions::INFOCLUBE_EDITAR);
    }
}
