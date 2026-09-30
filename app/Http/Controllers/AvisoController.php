<?php

namespace App\Http\Controllers;

use App\Models\Aviso;
use App\Models\AvisoView;
use App\Models\Lembrete;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\AvisoCreated;
use Illuminate\Http\Request;

class AvisoController extends Controller
{
    public function index(Request $request)
    {
        $user   = auth()->user();
        $search = $request->query('q');

        $avisos = Aviso::with('creator', 'lembretes', 'tags')
            ->visibleTo($user)
            ->search($search)
            ->active()
            ->orderByDesc('created_at')
            ->get();

        $expirados = Aviso::with('creator', 'lembretes', 'tags')
            ->visibleTo($user)
            ->search($search)
            ->expired()
            ->orderByDesc('created_at')
            ->get();

        $todos = Aviso::with('creator', 'lembretes', 'tags')
            ->visibleTo($user)
            ->search($search)
            ->orderByDesc('created_at')
            ->get();

        return view('avisos.index', compact('avisos', 'expirados', 'todos', 'search'));
    }

    public function show(Aviso $aviso)
    {
        $aviso->load('creator', 'lembretes', 'tags');

        AvisoView::create([
            'aviso_id'  => $aviso->id,
            'user_id'   => auth()->id(),
            'viewed_at' => now(),
        ]);

        // Avisos são de todo mundo logado: quem vê o aviso vê também quem o leu.
        // A privacidade (público, setor, grupo, pessoal) é do próprio aviso —
        // ver Aviso::scopeVisibleTo().
        $viewHistory = $aviso->views()->with('user:id,name')->get()
                ->groupBy('user_id')
                ->map(fn($entries) => [
                    'user'       => $entries->first()->user,
                    'last_view'  => $entries->first()->viewed_at,
                    'count'      => $entries->count(),
                ])
                ->sortByDesc('last_view')
                ->values();

        return view('avisos.show', compact('aviso', 'viewHistory'));
    }

    public function create()
    {
        $users = User::orderBy('name')->get(['id', 'name']);
        return view('avisos.create', compact('users'));
    }

    public function store(Request $request)
    {

        $data = $request->validate([
            'title'                      => 'required|string|max:200',
            'content'                    => 'nullable|string',
            'privacy'                    => 'required|in:pessoa,setor,publico,grupo',
            'user_ids'                   => 'required_if:privacy,grupo|nullable|array',
            'user_ids.*'                 => 'integer|exists:users,id',
            'expires_at'                 => 'nullable|date|after_or_equal:today',
            'lembretes'                  => 'nullable|array',
            'lembretes.*.remind_at'      => 'required|date|after:now',
            'tags'                       => 'nullable|array',
            'tags.*'                     => 'string|max:50',
        ]);

        $data['created_by'] = auth()->id();

        if ($request->hasFile('image')) {
            $imageName = time() . '.' . $request->image->extension();
            $request->image->move(public_path('images/avisos'), $imageName);
            $data['image'] = $imageName;
        }

        $aviso = Aviso::create($data);

        $this->syncLembretes($aviso, $request->input('lembretes', []));
        $this->syncTags($aviso, $request->input('tags', []));
        $this->syncUsers($aviso, $request->input('user_ids', []));

        $this->notifyUsers($aviso, new AvisoCreated($aviso));

        return redirect()->route('avisos.index')->with('success', 'Aviso criado com sucesso!');
    }

    public function edit(Aviso $aviso)
    {
        $aviso->load('lembretes', 'tags', 'users');
        $users = User::orderBy('name')->get(['id', 'name']);
        return view('avisos.edit', compact('aviso', 'users'));
    }

    public function update(Request $request, Aviso $aviso)
    {

        $data = $request->validate([
            'title'                 => 'required|string|max:200',
            'content'               => 'nullable|string',
            'privacy'               => 'required|in:pessoa,setor,publico,grupo',
            'user_ids'              => 'required_if:privacy,grupo|nullable|array',
            'user_ids.*'            => 'integer|exists:users,id',
            'expires_at'            => 'nullable|date',
            'lembretes'             => 'nullable|array',
            'lembretes.*.remind_at' => 'required|date',
            'tags'                  => 'nullable|array',
            'tags.*'                => 'string|max:50',
        ]);

        if ($request->hasFile('image')) {
            $this->deleteImage($aviso->image);
            $imageName = time() . '.' . $request->image->extension();
            $request->image->move(public_path('images/avisos'), $imageName);
            $data['image'] = $imageName;
        }

        if ($request->boolean('remove_image')) {
            $this->deleteImage($aviso->image);
            $data['image'] = null;
        }

        $aviso->update($data);

        $this->syncLembretes($aviso, $request->input('lembretes', []));
        $this->syncTags($aviso, $request->input('tags', []));
        $this->syncUsers($aviso, $request->input('user_ids', []));

        return redirect()->route('avisos.show', $aviso)->with('success', 'Aviso atualizado!');
    }

    public function destroy(Aviso $aviso)
    {
        $aviso->delete();
        return redirect()->route('avisos.index')->with('success', 'Aviso removido.');
    }

    private function syncLembretes(Aviso $aviso, array $lembretes): void
    {
        // Remove apenas os lembretes ainda não enviados
        $aviso->lembretes()->where('sent', false)->delete();

        foreach ($lembretes as $item) {
            if (!empty($item['remind_at'])) {
                Lembrete::create([
                    'aviso_id'  => $aviso->id,
                    'remind_at' => $item['remind_at'],
                ]);
            }
        }
    }

    /**
     * Sincroniza as tags do aviso. Nomes são normalizados (minúsculas, sem
     * espaços nas pontas) e tags inexistentes são criadas automaticamente.
     */
    private function syncTags(Aviso $aviso, array $tags): void
    {
        $ids = collect($tags)
            ->map(fn($name) => Tag::normalize($name))
            ->filter()
            ->unique()
            ->map(fn($name) => Tag::firstOrCreate(['name' => $name])->id)
            ->all();

        $aviso->tags()->sync($ids);
    }

    private function syncUsers(Aviso $aviso, array $userIds): void
    {
        $aviso->users()->sync($aviso->privacy === Aviso::PRIVACY_GRUPO ? $userIds : []);
    }

    private function notifyUsers(Aviso $aviso, $notification): void
    {
        $users = match ($aviso->privacy) {
            Aviso::PRIVACY_PESSOA  => User::where('id', $aviso->created_by)->get(),
            Aviso::PRIVACY_SETOR   => $this->usersInSameSetor($aviso),
            Aviso::PRIVACY_PUBLICO => User::all(),
            Aviso::PRIVACY_GRUPO   => $aviso->users,
        };

        $users->each(fn($user) => $user->notify($notification));
    }

    /**
     * Quem divide ao menos um setor com o criador — a mesma régua de
     * Aviso::scopeVisibleTo() para a privacidade "setor". (Antes era a role
     * do Spatie, e a notificação ia para gente que nem enxergava o aviso.)
     */
    private function usersInSameSetor(Aviso $aviso): \Illuminate\Support\Collection
    {
        $creator = User::with('sectors')->find($aviso->created_by);
        if (!$creator || $creator->sectors->isEmpty()) {
            return collect([$creator])->filter();
        }

        $sectorIds = $creator->sectors->pluck('id');
        return User::whereHas('sectors', fn($q) => $q->whereIn('sectors.id', $sectorIds))->get();
    }

    private function deleteImage(?string $filename): void
    {
        if ($filename && file_exists(public_path('images/avisos/' . $filename))) {
            unlink(public_path('images/avisos/' . $filename));
        }
    }
}
