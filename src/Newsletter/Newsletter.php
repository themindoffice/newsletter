<?php

namespace Modules\Addons;

use Modules\Addons\Newsletter\Install\Installer;

class Newsletter
{
    public function install()
    {
        require_once 'install/Installer.php';

        $installer = new Installer();
        $installer->run();
    }

    public function popup($body) {
        ob_start();
        include 'popup.php';
        return ob_get_clean();
    }

    public function content($newsletter_id)
    {
        $url = $_ENV['APP_DOMAIN'] . url('iris_nieuwsbrieven', $newsletter_id);
        return file_get_contents($url);
    }

    public function duplicate() {

        global $argv;

        $newsletter_to_duplicate = db()->table('iris_nieuwsbrieven')
            ->select()
            ->find($argv[2]);

        unset(
            $newsletter_to_duplicate['id'],
            $newsletter_to_duplicate['sent_at'],
            $newsletter_to_duplicate['modified']
        );

        $duplicated_newsletter_id = db()->table('iris_nieuwsbrieven')
            ->insert(array_merge($newsletter_to_duplicate, [
                'naam' => '[KOPIE] ' . output($newsletter_to_duplicate['naam']),
                'created' => time(),
            ]))
            ->execute();

        db()->table('seo')->insert([
            'tabel' => 'iris_nieuwsbrieven',
            'tabel_id' => $duplicated_newsletter_id,
            'page_url' => time(),
            'language' => 'nl'
        ])->execute();

        echo $this->popup('
            <h4 class="mb-4">Kopie gemaakt</h4>
            <p>Ververs de pagina om de nieuwsbrief te zien.</a>                        
        ');

    }

    public function cronjob() {

        $items = db()->table('iris_nieuwsbrieven_verzendlijst')
            ->select()
            ->whereNull('sent_at')
            ->limit(5)
            ->get();

        foreach($items ?? [] as $item) {

            db()->table('iris_nieuwsbrieven_verzendlijst')
                ->update(['sent_at' => time()])
                ->where('id', $item['id'])
                ->execute();

            $newsletter = db()->table('iris_nieuwsbrieven')
                ->select()
                ->find($item['iris_nieuwsbrieven_id']);

            email()
                ->sender([
                    'address' => $_ENV['MAIL_FROM_ADDRESS'],
                    'name' => $_ENV['MAIL_FROM_NAME']
                ])
                ->to($item['email'])
                ->type('plain')
                ->subject($newsletter['naam'])
                ->message($newsletter['html'])
                ->send();

        }
    }

    public function test() {

        global $argv;

        $newsletter = db()->table('iris_nieuwsbrieven')
            ->select()
            ->find($argv[2]);

        if ($argv[3] == 'popup') {

            $email = substr($argv[4], 0, strpos($argv[4], '?'));

            echo $this->popup('                       
                <h4 class="mb-4">Verstuur test mail</h4>
                <form action="/newsletter/test/'.$newsletter['id'].'/send" class="validate">
                    <input type="hidden" name="gender">
                    <input type="text" name="email" class="form-control mb-3" value="'.$email.'">
                    
                    <div class="feedback"></div>
                    
                    <button class="btn btn-warning w-100" type="submit">Verstuur test</button>
                </form>                        
            ');

        } else {

            //parse_str($_POST['data'], $_POST);

            validate([
                'email' => 'email',
            ], 'nl');

            email()
                ->sender([
                    'address' => $_ENV['MAIL_FROM_ADDRESS'],
                    'name' => $_ENV['MAIL_FROM_NAME']
                ])
                ->to($_POST['email'])
                ->type('plain')
                ->subject('[TEST] ' . (output($newsletter['onderwerpregel']) != '' ? output($newsletter['onderwerpregel']) : output($newsletter['naam'])))
                ->message(self::content($newsletter['id']))
                ->send();

            echo json_encode([
                'status' => 'success',
                'clear' => 'all',
                'message' => 'Je ontvangt binnen enkele minuten een test mail.'
            ]);
            exit();

        }
    }

    public function send()
    {
        global $argv;

        $newsletter = db()->table('iris_nieuwsbrieven')
            ->select()
            ->find($argv[2]);

        if(isset($argv[3])) {
            $argv[3] = substr($argv[3], 0, strpos($argv[3], '?'));
        }

        if (($argv[3] ?? '') == 'popup') {

            if ($newsletter['sent_at'] != '') {

                echo $this->popup('
                <h4 class="mb-4">Versturen</h4>
                
                <div class="text-center">            
                    <p>De nieuwsbrief is al verstuurd op ' . date('d-m-Y H:i') . '</p>                
                </div>
            ');

            } else {

                echo $this->popup('
                <h4 class="mb-4">Versturen</h4>
                
                <div class="text-center">            
                    <p>Wil je de nieuwsbrief echt versturen?</p>                
                    <a href="/newsletter/send/' . $newsletter['id'] . '" class="btn btn-success w-50">Ja, verstuur</a>
                </div>
            ');

            }

        } else {

            $content = self::content($newsletter['id']);

            db()->table('iris_nieuwsbrieven')
                ->update([
                    'html' => $content,
                    'sent_at' => time()
                ])->where('id', $newsletter['id'])
                ->execute();

            $contacts = db()->table('iris_nieuwsbrieven_contacten')
                ->select()
                ->where('lijsten_id', 'like', '%"'.$newsletter['lijsten_id'].'"%')
                ->get();

            foreach($contacts ?? [] as $contact) {

                $unsubscribed = db()->table('iris_nieuwsbrieven_uitschrijvingen')
                    ->select()
                    ->where('email', $contact['email'])
                    ->exists();


                if ($unsubscribed) { continue; }

                db()->table('iris_nieuwsbrieven_verzendlijst')->insert([
                    'iris_nieuwsbrieven_id' => $newsletter['id'],
                    'email' => $contact['email'],
                    'created' => time()
                ])->execute();
            }

            echo json_encode([
                'status' => 'success',
                'clear' => 'all',
                'message' => 'De nieuwsbrief is ingepland en zal binnen enkele minuten verstuurd worden.'
            ]);
            exit();

        }
    }
}
