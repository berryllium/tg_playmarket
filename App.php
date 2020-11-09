<?php
class App {
    private $item;
    private $path;
    private $url;
    private $new_rating;
    private $new_reviews;

    public function __construct($path) {
        $this->path = $path;
        $json = file_get_contents($this->path);
        $item = json_decode($json, true);
        $this->url = "https://play.google.com/store/apps/details?id=" . $item['id'];
        $this->item = $item;
    }

    public function validate() {
        $message = [];
        $this->parse();
        if($this->item['reviews'] != $this->new_reviews) {
            $this->item['reviews'] = $this->new_reviews;
            foreach($this->new_reviews as $rev) {
                if((int)$rev == 1) {
                    $message[] = $this->item['name'] . ': появился отзыв с рейтингом 1';
                }
            }
        }
        $diff = $this->item['rating'] - $this->new_rating;
        if($diff > 0) {
            $this->item['rating'] = $this->new_rating;
            $message[] = $this->item['name'] . ': рейтинг приложения упал на ' . $diff;
        }
        if($message) {
            foreach (USER_ID as $user) {
                $this->sendMessage($message, $user);
            }
        }
        $this->save();
    }

    public function parse() {
        $html = $this->getPage();
        // $html = file_get_contents('test.html');
        $dom = phpQuery::newDocument($html);
        $rating = $dom->find('.BHMmbe')->text();
        if($rating) $this->new_rating = $rating;
        else {
            $message = $this->item['name'] . ': приложение не доступно';
            foreach(USER_ID as $user) {
                $this->sendMessage($message, $user);
            }
            $this->item['availability'] = false;
            $this->save();
            return false;
        }
        
        $block = $dom->find(".LXrl4c")->html();
        $rev = preg_match_all('#aria-label="Rated (\S+) stars out of#', $block, $matches);
        $this->new_reviews = $matches[1];    
    }

    public function getPage() {
        $header = unserialize(file_get_contents('headers.txt'));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.183 Safari/537.36');
        $output = curl_exec($ch);
        file_put_contents('test.html', $output);
        curl_close($ch);
        return $output;
    }

    function sendMessage($message, $chat_id) {
        $message = implode(', ', $message);
        // file_put_contents('mess/mess_'.rand(1,1000).'.txt', $message);
        $method = 'sendMessage';
        $sendData = [
          'text' => $message,
          'chat_id' => $chat_id
        ];
        $this->sendTelegram($method, $sendData);
        return false;
      }

    function sendTelegram($method, $data, $headers = []) {
        $ch = curl_init();
        curl_setopt_array($ch, [
          CURLOPT_POST => 1,
          CURLOPT_HEADER => 0,
          CURLOPT_RETURNTRANSFER => 1,
          CURLOPT_URL => 'https://api.telegram.org/bot'. TOKEN .'/'.$method,
          CURLOPT_POSTFIELDS => json_encode($data),
          CURLOPT_HTTPHEADER => array_merge(["Content-Type: application/json"], $headers)
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
      }

    public function save() {
        var_dump($this->new_rating);
        $json = json_encode($this->item, JSON_UNESCAPED_UNICODE);
        file_put_contents($this->path, $json);
    }
}