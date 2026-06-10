<?php

namespace App\Http\Controllers;

use App\Models\Aviso;
use App\Models\AvisoView;
use App\Models\Lembrete;
use App\Models\User;
use App\Notifications\AvisoCreated;
use Illuminate\Http\Request;

class AvisoController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $avisos = Aviso::with('creator', 'lembretes')
            ->visibleTo($user)
            ->active()
            ->orderByDesc('created_at')
            ->get();

        $expirados = Aviso::with('creator', 'lembretes')
            ->visibleTo($user)
            ->expired()
            ->orderByDesc('created_at')
            ->get();

        $todos = Aviso::with('creator', 'lembretes')
            ->visibleTo($user)
            ->orderByDesc('created_at')
            ->get();

        return view('avisos.index', compact('avisos', 'expirados', 'todos'));
    }

    public function show(Aviso $aviso)
    {
        $aviso->load('creator', 'lembretes');

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
        ]);

        $data['created_by'] = auth()->id();

        if ($request->hasFile('image')) {
            $imageName = time() . '.' . $request->image->extension();
            $request->image->move(public_path('images/avisos'), $imageName);
            $data['image'] = $imageName;
        }

        $aviso = Aviso::create($data);

        $this->syncLembretes($aviso, $request->input('lembretes', []));

        $this->notifyUsers($aviso, new AvisoCreated($aviso));

        return redirect()->route('avisos.index')->with('success', 'Aviso criado com sucesso!');
    }

    public function edit(Aviso $aviso)
    {
        $this->authorizeManage();
        $aviso->load('lembretes');
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
