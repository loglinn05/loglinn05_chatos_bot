<?php

namespace App\Http\Controllers;

use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Http\Request;

class TrelloController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        if (
            $request['action']['display']['translationKey'] == 'action_move_card_from_list_to_list'
        ) {
            $this->notifyThatCardIsMoved($request);
        }
    }

    private function notifyThatCardIsMoved($request)
    {
        $cardName = $request['action']['display']['entities']['card']['text'];
        $listBefore = $request['action']['display']['entities']['listBefore']['text'];
        $listAfter = $request['action']['display']['entities']['listAfter']['text'];
        $fullName = $request['action']['memberCreator']['fullName'];
        $message = "A card named *$cardName* is moved from the *$listBefore* list to the *$listAfter* list by _{$fullName}_.";
        $chat = TelegraphChat::where('chat_id', '-4546863940')->first();
        $chat->message($message)->send();
    }
}
