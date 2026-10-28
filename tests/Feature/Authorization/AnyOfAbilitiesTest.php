<?php

namespace Tests\Feature\Authorization;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use Tests\Feature\Rebrand\Concerns\RendersScreens;
use Tests\TestCase;

/**
 * As habilidades compostas (Permissions::ANY_OF) viram Gates no
 * AppServiceProvider: cada uma abre com qualquer uma das permissões da lista
 * e com nenhuma outra. A busca de guias da Lara (IA) lê a mesma lista para
 * dizer o que pedir — se a lista e o Gate divergissem, a IA mandaria pedir a
 * permissão errada.
 */
class AnyOfAbilitiesTest extends TestCase
{
    use RendersScreens;

    public function test_toda_permissao_da_lista_existe_no_catalogo(): void
    {
        foreach (P::ANY_OF as $ability => $permissions) {
            $this->assertNotEmpty($permissions, $ability);
            $this->assertFalse(P::exists($ability), "{$ability} não pode ser também permissão do catálogo.");

            foreach ($permissions as $permission) {
                $this->assertTrue(P::exists($permission), "{$ability}: {$permission} não está no catálogo.");
            }
        }
    }

    public function test_cada_permissao_da_lista_abre_a_habilidade(): void
    {
        foreach (P::ANY_OF as $ability => $permissions) {
            foreach ($permissions as $permission) {
                $user = $this->usuario(new UserAccess([$permission]));
                $this->assertTrue($user->can($ability), "{$permission} deveria abrir {$ability}.");
            }
        }
    }

    public function test_permissao_de_fora_nao_abre(): void
    {
        $user = $this->usuario(new UserAccess([P::SIV_BUSCA]));

        foreach (array_keys(P::ANY_OF) as $ability) {
            $this->assertFalse($user->can($ability), "SIV › Busca não deveria abrir {$ability}.");
        }
    }

    public function test_regras_que_valiam_antes_da_lista(): void
    {
        // As mesmas combinações que o AppServiceProvider definia à mão.
        $this->assertTrue($this->usuario(new UserAccess([P::ASSINATURA_TERMO_MENORES_PAREAR]))->can('acessar-guia-assinatura'));
        $this->assertTrue($this->usuario(new UserAccess([P::INGRESSOS_CORTESIAS]))->can('ver-caixa-ingressos'));
        $this->assertFalse($this->usuario(new UserAccess([P::INGRESSOS_CORTESIAS]))->can('acessar-ingressos'));
        $this->assertFalse($this->usuario(new UserAccess([P::INGRESSOS_CHECKIN]))->can('ver-caixa-ingressos'));
    }

    public function test_granted_by(): void
    {
        $this->assertSame([P::SIV_BUSCA], P::grantedBy(P::SIV_BUSCA));
        $this->assertSame(P::ANY_OF['acessar-ingressos'], P::grantedBy('acessar-ingressos'));
        $this->assertSame([], P::grantedBy('coordinate-sector'));
    }
}
