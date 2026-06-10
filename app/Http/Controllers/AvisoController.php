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

        $canManage = auth()->user()->can('manage avisos');
        $viewHistory = $canManage
            ? $aviso->views()->with('user:id,name')->get()
                ->groupBy('user_id')
                ->map(fn($entries) => [
                    'user'       => $entries->first()->user,
                    'last_view'  => $entries->first()->viewed_at,
                    'count'      => $entries->count(),
                ])
                ->sortByDesc('last_view')
                ->values()
            : collect();

        return view('avisos.show', compact('aviso', 'canManage', 'viewHistory'));
    }

    public function create()
    {
        $this->authorizeManage();
        return view('avisos.create');
    }

    public function store(Request $request)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'title'                      => 'required|string|max:200',
            'content'                    => 'nullable|string',
            'privacy'                    => 'required|in:pessoa,setor,publico',
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

        $this->notifyUsers($aviso, new AvisoCreated($aviso));

        return redirect()->route('avisos.index')->with('success', 'Aviso criado com sucesso!');
    }

    public function edit(Aviso $aviso)
    {
        $this->authorizeManage();
        $aviso->load('lembretes', 'tags');
        return view('avisos.edit', compact('aviso'));
    }

    public function update(Request $request, Aviso $aviso)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'title'                 => 'required|string|max:200',
            'content'               => 'nullable|string',
            'privacy'               => 'required|in:pessoa,setor,publico',
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

        return redirect()->route('avisos.show', $aviso)->with('success', 'Aviso atualizado!');
    }

    public function destroy(Aviso $aviso)
    {
        $this->authorizeManage();
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

    private function notifyUsers(Aviso $aviso, $notification): void
    {
        $users = match ($aviso->privacy) {
            Aviso::PRIVACY_PESSOA  => User::where('id', $aviso->created_by)->get(),
            Aviso::PRIVACY_SETOR   => $this->usersInSameSetor($aviso),
            Aviso::PRIVACY_PUBLICO => User::all(),
        };

        $users->each(fn($user) => $user->notify($notification));
    }

    private function usersInSameSetor(Aviso $aviso): \Illuminate\Support\Collection
    {
        $creator = User::with('roles')->find($aviso->created_by);
        if (!$creator || $creator->roles->isEmpty()) {
            return collect([$creator])->filter();
        }

        $roleNames = $creator->roles->pluck('name');
        return User::whereHas('roles', fn($q) => $q->whereIn('name', $roleNames))->get();
    }

    private function deleteImage(?string $filename): void
    {
        if ($filename && file_exists(public_path('images/avisos/' . $filename))) {
            unlink(public_path('images/avisos/' . $filename));
        }
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()->can('manage avisos'), 403);
    }
}
