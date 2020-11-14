<?php
error_reporting(E_ALL);
class App {
    private $item;
    private $path;
    private $url;
    private $new_rating;
    private $new_reviews;
    private $new_date;

    public function __construct($path) {
        $this->path = $path;
        $json = file_get_contents($this->path);
        $item = json_decode($json, true);
        $this->url = "https://play.google.com/store/apps/details?id=" . $item['id'];
        $this->item = $item;
    }

    public function validate() {
        $message = [];
        if(!$this->parse()) {
            if($this->item['availability'] != $this->new_availability) {
                $this->item['availability'] = $this->new_availability;    
                $message[] = $this->item['name'] . ': приложение не доступно';
                foreach (USER_ID as $user) {
                    $this->sendMessage($message, $user);
                }
            }
            $this->save();
            return false;
        };
        if($this->item['reviews'] != $this->new_reviews && $this->new_reviews) {
            $this->item['reviews'] = $this->new_reviews;
            foreach($this->new_reviews as $rev) {
                if((int)$rev == 1) {
                    $message[] = $this->item['name'] . ': появился отзыв с рейтингом 1';
                }
            }
        }
        if($this->new_rating) {
            $diff = $this->item['rating'] - $this->new_rating;
        $this->item['rating'] = $this->new_rating;
        if($diff > 0) {
            $message[] = $this->item['name'] . ': рейтинг приложения упал на ' . $diff;
        } 
        }
        
        if($this->item['date'] != $this->new_date && $this->new_date){
            $this->item['date'] = $this->new_date;
            $message[] = $this->item['name'] . ': приложение обновлено ';
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
        $error = $dom->find('#error-section')->text();

        if($error) {
            $this->new_availability = false;
            return false;
        } else {
            $this->new_availability = true;
        }

        $rating = $dom->find('.BHMmbe')->text();
        if($rating) $this->new_rating = $rating;
        
        $block = $dom->find(".LXrl4c")->html();
        $rev = preg_match_all('#aria-label="Rated (\S+) stars out of#', $block, $matches);
        if($matches[1]) $this->new_reviews = $matches[1];    
        
        $block = $dom->find(".hAyfc")->html();
        $rev = preg_match('#<span class="htlgb">([^<]+)</span>#', $html, $matches);
        if($matches[1]) $this->new_date = $matches[1];  
        return true;
    }

    public function getPage() {
        $header = unserialize(file_get_contents('headers.txt'));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.183 Safari/537.36');
        $output = curl_exec($ch);
        // file_put_contents('test.html', $output);
        curl_close($ch);
        return $output;
    }

    function sendMessage($message, $chat_id) {
        if(!$message) return false;
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
        $json = json_encode($this->item, JSON_UNESCAPED_UNICODE);
        file_put_contents($this->path, $json);
    }
}