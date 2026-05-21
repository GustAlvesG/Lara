<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\TelegramService;

class TelegramContactController extends Controller
{
    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }


    public function find(Request $request)
    {
        $query = $request->all();

        $contacts = $this->telegramService->findContacts($query);
        return response()->json($contacts);
    }

    public function store(Request $request)
    {
        $data = $request->all();

        $contact = $this->telegramService->createContact($data);

        return response()->json($contact, 201);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string',
            'chat_id' => 'sometimes|required|string|unique:contact_telegram,chat_id,' . $id,
            'phone' => 'nullable|string',
        ]);

        $contact = $this->telegramService->updateContact($id, $data);

        return response()->json($contact);
    }
}
