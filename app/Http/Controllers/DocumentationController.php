<?php

namespace App\Http\Controllers;

use App\Services\DocumentationService;

class DocumentationController extends Controller
{
    public function __construct(private DocumentationService $documentation) {}

    /**
     * Abre a documentação no primeiro documento (índice).
     */
    public function index()
    {
        return redirect()->route('docs.show', 'README');
    }

    /**
     * Renderiza um documento de documentação a partir do slug.
     */
    public function show(string $slug = 'README')
    {
        $path = $this->documentation->resolve($slug);

        abort_if($path === null, 404);

        return view('documentation.show', [
            'tree' => $this->documentation->tree(),
            'html' => $this->documentation->render($path),
            'title' => $this->documentation->title($path),
            'currentSlug' => preg_replace('/\.md$/i', '', $slug),
        ]);
    }
}
