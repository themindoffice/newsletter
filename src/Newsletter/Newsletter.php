<?php

namespace Modules\Addons;

use Modules\Addons\Newsletter\Install\Installer;

class Newsletter
{

    public function __construct()
    {
        //
    }
    
    public function locator()
    {
        echo 'Test';
    }

    public function install()
    {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/Addons/Newsletter/install/Installer.php';

        $installer = new Installer();

        $installer->tables();
        $installer->components();
    }

    public function copy(){

        global $argv;

        $nieuwsbrief = db()->table('nieuwsbrieven')->select()->where('id',$argv[2])->first();

        $id = db()->table('nieuwsbrieven')->insert(array(
            "naam" => "Copy: ".$nieuwsbrief["naam"],
            "omschrijving" => $nieuwsbrief["omschrijving"],
            "header_knop_titel" => $nieuwsbrief["header_knop_titel"],
            "header_knop_link" => $nieuwsbrief["header_knop_link"],
            "header_achtergrond" => $nieuwsbrief["header_achtergrond"],
            "send" => 0,
            "created" => time()
        ))->execute();

        db()->table('seo')->insert(array(
            "tabel" => 'nieuwsbrieven',
            "tabel_id" => $id,
            "page_url" => time(),
            "language" => 'nl'
        ))->execute();

        $items = db()->table('nieuwsbrieven_items')
            ->select()
            ->where('nieuwsbrieven_id', $argv[2])
            ->orderBy('volgorde')
            ->get();

        foreach($items as $item){

            db()->table('nieuwsbrieven_items')->insert(array(
                "naam" => $item["naam"],
                "active" => $item["active"],
                "nieuwsbrieven_id" => $id,
                "type" => $item["type"],
                "titel" => $item["titel"],
                "tekst" => $item["tekst"],
                "afbeelding" => $item["afbeelding"],
                "knop_link" => $item["knop_link"],
                "knop_link_titel" => $item["knop_link_titel"],
                "confetti_tonen" => $item["confetti_tonen"],
                "foto_locatie" => $item["foto_locatie"],
                "rubriek" => $item["rubriek"],
                "volgorde" => $item["volgorde"]
            ))->execute();

        }


        echo $s = $this->makePopup('
            <h4 class="mb-4">Kopie gemaakt</h4>
            <p>Ververs de pagina om de nieuwsbrief te zien.</a>
                        
        ');

    }


    public function cronJob(){

        $alleContacten = db()->table('nieuwsbrieven_verzendlijst')->select()->where('send',0)->limit(5)->get();

        foreach($alleContacten as $contact) {

            db()->table('nieuwsbrieven_verzendlijst')->update(array('send' => 1))->where('id', $contact["id"])->execute();

            $nb = db()->table('nieuwsbrieven')->select()->where('id', $contact["nieuwsbrieven_id"])->first();

            $mail = email()
                ->sender('nieuwsbrief@150jaarcomite.nl')
                ->to($contact["e_mail"])
                ->type('plain')
                ->subject($nb["naam"])
                ->message($nb["html"]);
            $mail->send();
        }
    }

    private function makePopup($body){

        return '<html>
                    <head>
                        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
                        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuRlEzO+tcaEPQogQ0KaoGN26/zrn20ImR1DfuLWnOo7aBA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
                        <link rel="stylesheet" href="https://iris.themindoffice.dev/css/themes/legacy.css?v=2022011004" />
                        <style>
                        body{
                        font-family: \'Open Sans\'  !important;
                        }</style>
                    </head>
                        <body style="background-color: #F8F9FC;">
                            <div class="p-4">
                                '.$body.'
                            </div>
                        <script src="/assets/js/jquery.min.js"></script>
                        <script src="/assets/js/axios/axios.min.js"></script>
                        <script src="/assets/js/validate.js"></script>
                    </body>
               </html>';
    }

    public function sendNewsletter(){


        global $argv;

        if ($argv[2] == "newsletter") {

            $added = array();
            $newsletterId = $_POST["id"];

            $nieuwsbrief = db()->table('nieuwsbrieven')->select()->where('id', $newsletterId)->first();
            if ($nieuwsbrief["send"] == 1){
                echo json_encode(['status' => 'error',  'message' => 'De nieuwsbrief is al verstuurd']);
                exit();
            }


            $url = 'https://150jaarcomite.nl' . url('nieuwsbrieven', $newsletterId);
            $content = file_get_contents($url);

            db()->table('nieuwsbrieven')->update(array('html' => $content, 'send' => 1))->where('id', $newsletterId)->execute();

            $alleContacten = db()->table('nieuwsbrieven_contacten')->select()->get();
            foreach($alleContacten as $contact){
                if (!in_array($contact["e_mail"],$added)){
                    $added[] = $contact["e_mail"];

                    $blacklist = db()->table('nieuwsbrieven_uitschrijvingen')->select()->where('email', $contact["e_mail"])->first();

                    if ($blacklist == false) {
                        db()->table('nieuwsbrieven_verzendlijst')->insert(array(
                            "nieuwsbrieven_id" => $newsletterId,
                            "naam" => $contact["naam"],
                            "e_mail" => $contact["e_mail"],
                            "send" => 0,
                            "created" => time()
                        ))->execute();
                    }
                }
            }

            $alleContacten = db()->table('nieuwsbriefinschrijvingen')->select()->get();
            foreach($alleContacten as $contact){
                if (!in_array($contact["email"],$added)){
                    $added[] = $contact["email"];
                    $blacklist = db()->table('nieuwsbrieven_uitschrijvingen')->select()->where('email', $contact["e_mail"])->first();

                    if ($blacklist == false) {
                        db()->table('nieuwsbrieven_verzendlijst')->insert(array(
                            "nieuwsbrieven_id" => $newsletterId,
                            "naam" => '',
                            "e_mail" => $contact["email"],
                            "send" => 0,
                            "created" => time()
                        ))->execute();
                    }
                }
            }


            echo json_encode(['status' => 'success', 'clear' => 'all', 'message' => 'De nieuwsbrief is ingepland en zal binnen enkele minuten verstuurd worden.']);
            exit();

        }

        if ($argv[2] == "test") {
            validate([
                'email' => 'email',
            ], 'nl');

            $newsletterId = $_POST["id"];

            $info = db()->table('nieuwsbrieven')->select()->where('id', $newsletterId)->first();
            $url = 'https://150jaarcomite.nl' . url('nieuwsbrieven', $newsletterId);
            $content = file_get_contents($url);

            $mail = email()
                ->sender('nieuwsbrief@150jaarcomite.nl')
                ->to($_POST["email"])
                ->type('plain')
                ->subject($info["naam"])
                ->message($content);
            $mail->send();

            echo json_encode(['status' => 'success', 'clear' => 'all', 'message' => 'Je ontvangt binnen enkele minuten een test mail.']);
            exit();
        }

    }

    public function viewSend()
    {
        global $argv;

        $nieuwsbrief = db()->table('nieuwsbrieven')->select()->where('id', $argv[2])->first();


        if ($nieuwsbrief["send"] == 1) {
            $s = $this->makePopup('
            <h4 class="mb-4">Verstuurd</h4>
           <div class="d-flex w-100">
                         <a href="/newsletter/viewSendTest/' . $argv[2] . '/' . explode("?", $argv[3])[0] . '" style="width: 50%;cursor: pointer" class="card btn mr-3 d-block">
                        <div class="card-body text-center">
                            <i class="fas fa-envelope p-2 text-dark" style="font-size: 50px;"></i>
                            <h4 class="mt-4 text-dark">Test mail</h4>
                        </div>
                    </a>
                    
                    
                       <span class="card btn ml-3 d-block">
                        <div class="card-body text-center">
                            <i class="fas fa-paper-plane p-2 text-dark" style="font-size: 50px;"></i>
                            <h4 class="mt-4 text-dark">De nieuwsbrief is al verstuurd</h4>
                        </div>
                    </span>
                    
            </div>
            </div>
                        
        ');
        }else{
            $s = $this->makePopup('
            <h4 class="mb-4">Versturen</h4>
            <div class="d-flex w-100">
                         <a href="/newsletter/viewSendTest/' . $argv[2] . '/' . explode("?", $argv[3])[0] . '" style="width: 50%;cursor: pointer" class="card btn mr-3 d-block">
                        <div class="card-body text-center">
                            <i class="fas fa-envelope p-2 text-dark" style="font-size: 50px;"></i>
                            <h4 class="mt-4 text-dark">Test mail</h4>
                        </div>
                    </a>
                    
                    
                       <a href="/newsletter/viewSendNewsletter/' . $argv[2] . '"  style="width: 50%;cursor: pointer" class="card btn ml-3 d-block">
                        <div class="card-body text-center">
                            <i class="fas fa-paper-plane p-2 text-dark" style="font-size: 50px;"></i>
                            <h4 class="mt-4 text-dark">Verstuur nieuwsbrief</h4>
                        </div>
                    </a>
                    
            </div>
                        
        ');
        }
        echo $s;
        exit();
    }


    public function viewSendNewsletter()
    {
        global $argv;

        $nieuwsbrieven = db()->table('nieuwsbrieven')->select()->where('id',$argv["2"])->first();
        $contacten = db()->table('nieuwsbrieven_contacten')->select()->get();


        $s = $this->makePopup('
                        <a href="/newsletter/viewSend/'.$argv[2].'" class="float-right" style="color:#000000"><b>Terug</b></a>
                        <h4 class="mb-3">Verstuur nieuwsbrief</h4>
                        <table class="table">
                            <tr><td width="250"><b>Aantal aanmeldingen</b></td><td>'.count($contacten).'</td></tr>
                            <tr><td><b>Onderwerp regel</b></td><td>'.$nieuwsbrieven["naam"].'</td></tr>
                        </table>
                        <form action="/newsletter/sendNewsletter/newsletter" class="validate">
                            <input type="hidden" name="gender">
                            <input type="hidden" name="id" value="'.$argv[2].'">
                            <div class="feedback"></div>
                            <button class="btn btn-warning w-100" type="submit">Versturen nieuwsbrief</button>
                        </form>
                        
        ');
        echo $s;
        exit();
    }


    public function viewSendDone()
    {
        global $argv;

        $s = $this->makePopup('
                      
                        <h2 class="mb-4">Nieuwsbrief verstuurd</h2>
                        Done
                        </form>
                        
        ');
        echo $s;
        exit();
    }
    public function viewSendTest()
    {
        global $argv;

        $s = $this->makePopup('
                        <a href="/newsletter/viewSend/'.$argv[2].'" class="float-right" style="color:#000000"><b>Terug</b></a>
                        <h4 class="mb-4">Verstuur test mail</h4>
                        <form action="/newsletter/sendNewsletter/test" class="validate">
                        <input type="hidden" name="gender">
                        <input type="hidden" name="id" value="'.$argv[2].'">
                        <input type="text" class="form-control mb-3" name="email" value="'.$argv[3].'">
                        <div class="feedback"></div>
                        <button class="btn btn-warning w-100" type="submit">Versturen test mail</button>
                        </form>
                        
        ');
        echo $s;
        exit();
    }
}
