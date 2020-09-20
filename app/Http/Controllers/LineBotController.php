<?php

namespace App\Http\Controllers;

use App\Services\Gurunavi;
use App\Services\RestaurantBubbleBuilder;
use Illuminate\Http\Request;
// Logを表示させる
use Illuminate\Support\Facades\Log;

use LINE\LINEBot;
// オウム返しの処理
use LINE\LINEBot\Event\MessageEvent\TextMessage;
// LINEBotのクラスを生成
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\MessageBuilder\FlexMessageBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\CarouselContainerBuilder;
class LineBotController extends Controller
{
  public function index()
  {
    return view('linebot.index');
  }

  public function restaurants(Request $request)
  {
    // Logをstorageに表示させる
     Log::debug($request->header());
     Log::debug($request->input());

    //  LINEBotクラスの生成
     $httpClient = new CurlHTTPClient(env('LINE_ACCESS_TOKEN'));
     $lineBot = new LINEBot($httpClient,['channelSecret' => env('LINE_CHANNEL_SECRET')]);

    //  署名の検証
    $signature = $request->header('x-line-signature');
    if(!$lineBot->validateSignature($request->getContent(),$signature)) {
      abort(400, 'invalid signature');
    }
    
    // リクエストからイベントを取り出す
    $events = $lineBot->parseEventRequest($request->getContent(),$signature);
    Log::debug($events);

    // オウム返しの処理内容
    foreach($events as $event) {
      if(!($event instanceof TextMessage)) {
        Log::debug('Non text message has come');
        continue;
      }
      $gurunavi = new Gurunavi();
      $gurunaviResponse = $gurunavi->searchRestaurants($event->getText());

      if (array_key_exists('error', $gurunaviResponse)) {
          $replyText = $gurunaviResponse['error'][0]['message'];
          $replyToken = $event->getReplyToken();
          $lineBot->replyText($replyToken, $replyText);
          continue;
      }
      $bubbles = [];
      foreach ($gurunaviResponse['rest'] as $restaurant) {
        $bubble = RestaurantBubbleBuilder::builder();
        $bubble->setContents($restaurant);
        $bubbles[] = $bubble;
      }

      $carousel = CarouselContainerBuilder::builder();
      $carousel->setContents($bubbles);

      $flex = FlexMessageBuilder::builder();
      $flex->setAltText('飲食店検索結果');
      $flex->setContents($carousel);

      $lineBot->replyMessage($event->getReplyToken(), $flex);
    }
  }
}
