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

        global $argv;

        if ($argv[2] == 'send') {

            $items = db()->table('iris_nieuwsbrieven_verzendlijst')
                ->select()
                ->whereNull('sent_at')
                ->limit(25)
                ->get();

            foreach($items ?? [] as $item) {

                $newsletter = db()->table('iris_nieuwsbrieven')
                    ->select()
                    ->find($item['iris_nieuwsbrieven_id']);

                $html = str_replace(['{address}'] , [$item['email']], $newsletter['html']);

                $response = email('mandrill')
                    ->to($item['email'])
                    ->subject($newsletter['naam'])
                    ->html($html)
                    ->send();

                if (is_array($response)) {

                    $response = reset($response);

                    $mandrill = [
                        'id' => $response['_id']
                    ];

                    ksort($response);

                    db()->table('iris_nieuwsbrieven_verzendlijst')
                        ->update([
                            'sent_at' => time(),
                            'mandrill' => json_encode($mandrill)
                        ])
                        ->where('id', $item['id'])
                        ->execute();

                }

            }

        } elseif ($argv[2] == 'info') {

            $items = db()->table('iris_nieuwsbrieven_verzendlijst')
                ->select()
                ->where('sent_at', '<=', strtotime('-30'))
                ->whereNull('info_checked_at')
                ->limit(25)
                ->get();

            foreach ($items ?? [] as $item) {

                $mandrill = json_decode($item['mandrill'], true);

                $info = email('mandrill')->info($mandrill['id']);

                $mandrill = [
                    'id' => $mandrill['id']
                ];

                foreach ($info ?? [] as $k => $v) {
                    if (!in_array($k, ['state', 'opens', 'clicks']) && !preg_match('/(_reason|_description)/', $k)) { continue; }
                    $mandrill[$k] = $v;
                }

                db()->table('iris_nieuwsbrieven_verzendlijst')
                    ->update([
                        'mandrill' => json_encode($mandrill),
                        'info_checked_at' => time()
                    ])->where('id', $item['id'])
                    ->execute();

            }
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

            validate([
                'email' => 'email',
            ], 'nl');

            email('mandrill')
                ->to($_POST['email'])
                ->subject('[TEST] ' . (output($newsletter['onderwerpregel']) != '' ? output($newsletter['onderwerpregel']) : output($newsletter['naam'])))
                ->html(self::content($newsletter['id']))
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

    public function unsubscribe()
    {
        global $argv;

        if (count($argv) != 4) { abort(404); }

        $list = db()->table('iris_nieuwsbrieven_lijsten')
            ->select()
            ->where('hash', $argv[2])
            ->first();

        $contact = db()->table('iris_nieuwsbrieven_contacten')
            ->select()
            ->where('email', $argv[3])
            ->where('lijsten_id', 'like', '%"'.$list['id'].'"%')
            ->first();

        if (!$list || !$contact) { abort(404); }

        $contact_lists = json_decode($contact['lijsten_id']) ?? [];
        $key = array_search($list['id'], $contact_lists);

        unset($contact_lists[$key]);

        db()->table('iris_nieuwsbrieven_uitschrijvingen')->insert([
            'lijsten_id' => $list['id'],
            'email' => $contact['email'],
            'created' => time()
        ])->execute();

        db()->table('iris_nieuwsbrieven_contacten')
            ->update([
                'lijsten_id' => json_encode($contact_lists)
            ])->where('id', $contact['id'])
            ->execute();

        echo 'U bent succesvol uitgeschreven van deze maillijst...';
    }

    public function import()
    {
        $file = file(__dir__ . '/subscribers.csv');

        $rows = array_map('str_getcsv', $file);
        $headers = array_shift($rows);

        $subscribers = [];

        foreach ($rows ?? [] as $row) {
            $subscribers[] =  array_combine($headers, $row);
        }

        foreach ($subscribers ?? [] as $email => $lists) {

//            $exists = db()->table('iris_nieuwsbrieven_contacten')
//                ->select()
//                ->where('email', $email)
//                ->exists();
//
//            if ($exists) { continue; }
//
//            $list_ids = [];
//
//            foreach ($lists ?? [] as $details) {
//
//                if (strtolower($details['lijst']) == 'niet in segment') { continue; }
//
//                $list_id = db()->table('iris_nieuwsbrieven_lijsten')
//                        ->select()
//                        ->where('naam', $details['lijst'])
//                        ->first()['id'] ?? null;
//
//                if (!$list_id) {
//
//                    $list_id = db()->table('iris_nieuwsbrieven_lijsten')->insert([
//                        'hash' => sha1(time() * rand(1, 999)),
//                        'naam' => $details['lijst']
//                    ])->execute();
//
//                }
//
//                $list_ids[] = (string) $list_id;
//            }
//
//            $list_ids = array_unique($list_ids);
//
//            if (count($list_ids) == 0) { continue; }
//
//            db()->table('iris_nieuwsbrieven_contacten')->insert([
//                'voornaam' => $details['voornaam'],
//                'achternaam' => $details['achternaam'],
//                'email' => $email,
//                'lijsten_id' => json_encode($list_ids)
//            ])->execute();
        }
    }
}
