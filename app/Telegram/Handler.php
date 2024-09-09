<?php

namespace App\Telegram;

use App\Models\User;
use DefStudio\Telegraph\Enums\ChatActions;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use GuzzleHttp\Client;

class Handler extends WebhookHandler
{
  private $trelloApiBaseUrl;
  private $trelloApiKey;
  private $trelloApiToken;
  private $boardId;

  public function __construct()
  {
    $this->trelloApiBaseUrl = env("TRELLO_API_BASE_URL");
    $this->trelloApiKey = env("TRELLO_API_KEY");
    $this->trelloApiToken = env("TRELLO_API_TOKEN");
    $this->boardId = env("BOARD_ID");
  }

  public function start()
  {
    $from = $this->message->from();
    $fromUser = User::where('telegram_user_id', $from->id())->first();

    Telegraph::chatAction(ChatActions::TYPING)->send();

    // saying hello
    $name = $from->firstName();
    $this->reply("Hi, $name!");

    // adding a user to the database if they're not there
    if (!$fromUser) {
      $user = new User();
      $user->telegram_user_id = $from->id();
      $user->first_name = $from->firstName();

      if ($from->lastName()) {
        $user->last_name = $from->lastName();
      }

      $user->telegram_username = $from->username();
      $user->save();

      if ($user) {
        $this->reply("You've been added to the database.");
      }
    }

    $this->reply("If you need further assistance, enter /help.");
  }

  public function help()
  {
    $this->reply("
      I can invite you to our Trello board. Let's work _together_! \xE2\x9C\xA8
      \n*Enter /invite to proceed.*
    ");
  }

  public function invite()
  {
    $from = $this->message->from();
    $fromUser = User::where('telegram_user_id', $from->id())->first();

    $this->forceToEnterEmail();

    $fromUser->status = "providing email";
    $fromUser->save();
  }

  public function handleUnknownCommand(\Illuminate\Support\Stringable $text): void
  {
    $this->reply("What do you mean?
    \nI don't speak humanese! \xF0\x9F\x98\x85");
  }

  public function handleChatMessage($email): void
  {
    $from = $this->message->from();
    $fromUser = User::where('telegram_user_id', $from->id())->first();

    if ($fromUser->status == "providing email") {
      $fullName = [];

      // set the user's email if it's not set
      // and set the full name for the invitation request
      if ($fromUser) {
        $fullName = ['fullName' => "{$fromUser->first_name} {$fromUser->last_name}"];
      }

      $invitationResponse = $this->sendInvitationRequest($email, $fullName);

      if (
        $invitationResponse->getStatusCode() == 200
      ) {
        if (!$fromUser->email) {
          $fromUser->email = $email;
          $fromUser->save();
        }
        $this->onSuccessfulInvitation();
      } elseif (
        $invitationResponse->getBody() == 'Member already invited'
      ) {
        $this->clearFromUserStatus();
        $this->reply("You're already invited. Enjoy the experience!");
        $this->getLinkToTheBoard();
      } elseif (
        json_decode($invitationResponse->getBody())->message == "invalid email address"
      ) {
        $this->reply("Invalid email address.");
        $this->forceToEnterEmail();
      } else {
        $this->clearFromUserStatus();
        $this->reply("Unknown error occurred during invitation attempt.");
      }
    }
  }

  private function forceToEnterEmail()
  {
    Telegraph::chatAction(ChatActions::TYPING)->send();
    $this->chat->message("Please, enter your email so I can invite you:")->forceReply("Enter your email here...")->send();
  }

  private function sendInvitationRequest($email, $fullName)
  {
    $client = new Client();
    $invitationResponse = $client->put(
      "$this->trelloApiBaseUrl/boards/$this->boardId/members?email=$email&key=$this->trelloApiKey&token=$this->trelloApiToken",
      [
        'http_errors' => false,
        'json' => $fullName
      ]
    );
    return $invitationResponse;
  }

  private function clearFromUserStatus()
  {
    $from = $this->message->from();
    $fromUser = User::where('telegram_user_id', $from->id())->first();

    $fromUser->status = null;
    $fromUser->save();
  }

  private function onSuccessfulInvitation()
  {
    $this->clearFromUserStatus();

    $this->reply("You've been successfully invited.");
    $this->getLinkToTheBoard();
  }

  private function getLinkToTheBoard()
  {
    $client = new Client();

    $getBoardResponse = $client->get("$this->trelloApiBaseUrl/boards/$this->boardId?key=$this->trelloApiKey&token=$this->trelloApiToken");

    if ($getBoardResponse->getStatusCode() == 200) {
      $linkToTheBoard = json_decode($getBoardResponse->getBody())->url;
      $this->reply("Here's the link to the board:
      \n$linkToTheBoard");
    } else {
      $this->reply("Unfortunately, we couldn't get the link to the board.");
    }
  }
}
