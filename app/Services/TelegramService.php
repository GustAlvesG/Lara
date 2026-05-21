<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\TelegramContact;


class TelegramService
{
    public function findContacts($param)
    {
        //param is a json with some of the fields: name, chat_id, phone
        $query = TelegramContact::query();
        
        if (isset($param['name'])) {
            $query->where('name', 'like', '%' . $param['name'] . '%');
        }

        if (isset($param['chat_id'])) {
            $query->where('chat_id', $param['chat_id']);
        }

        if (isset($param['phone'])) {
            $query->where('phone', 'like', '%' . $param['phone'] . '%');
        }

        return $query->get();
    }

    public function createContact($data)
    {
        return TelegramContact::create($data);
    }

    public function updateContact($id, $data)
    {
        $contact = TelegramContact::findOrFail($id);
        $contact->update($data);
        return $contact;
    }
}