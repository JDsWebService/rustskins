<?php

namespace App\Console\Commands;

use App\Models\RustID;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;


class ImportRustIDs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:ids';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports Rust Item ID\'s from the CorrosionHour website.';
    /**
     * @var string
     */
    private $sourceCode;
    /**
     * @var string
     */
    private $uri;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->uri = 'https://www.corrosionhour.com';
        $this->sourceCode = null;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $url = "https://www.corrosionhour.com/rust-item-list/";
        $this->line("\nGrabbing Rust Item ID's from Page URL\n");
        $this->line("Working on: {$url}");
        // Get the source code for each url
        if($this->getSourceCode($url) != 200) {
            $this->alert("This url {$url} returned a non-working status code. This must be the last page.");
        }
        // Create a new Symfony DOMCrawler and filter to only content in the body tag
        $crawler = new Crawler($this->sourceCode, $this->uri);
        $crawler = $crawler->filter('body');

        // Search Term for Crawler Filter (Each Item Link)
        $results = $crawler->filter('table.ch-table tbody tr');

        // Loop through all search results and add URL's to array.
        $this->line("Searching {$url} source code for item data...");
        $results->each(function (Crawler $node, $i) {
            $fullname = $this->getSteamName($node->filter('td.ch-tbl-name')->text());
            $shortname = $node->filter('td.ch-tbl-short-name')->text();
            $itemID = $node->filter('td.ch-tbl-id')->text();
            $description = $node->filter('td.ch-tbl-desc')->text();
            $default_stack_size = $node->filter('td.ch-tbl-stack-size')->text();
            $item = [
                'fullname' => $fullname,
                'shortname' => $shortname,
                'itemID' => $itemID,
                'description' => $description,
                'default_stack_size' => $default_stack_size
            ];
            $this->saveItemToDatabase($item);
        });
        $this->info("Grabbed all the data from the url {$url}\n");

        $this->line("Adding Custom Rust ID's To Database");
        $this->addCustomIDsToDatabase();
        $this->info("Custom ID's saved to database");
    }

    private function getSourceCode(string $url)
    {
        $this->line('Grabbing Source Code from ' . $url);
        // Create a new Guzzle Client
        $client = new Client();
        try {
            $response = $client->request('GET', $url, ['allow_redirects' => false]);
        } catch (GuzzleException $e) {
            $this->line("\n");
            return $this->alert($e->getMessage());
        }
        if($response->getStatusCode() == 200) {
            $this->sourceCode = $response->getBody()->getContents();
            $this->info("Grabbed Source Code Successfully from: {$url}");
        } else {
            $this->alert("The url ({$url}) returned a status code of {$response->getStatusCode()}");
        }
        return $response->getStatusCode();
    }

    private function saveItemToDatabase(array $itemArray)
    {
        $item = RustID::where('fullname', $itemArray['fullname'])->first();
        if($item != null) {
            $this->warn("Item {$itemArray['fullname']} has already been added in the database! Skipping Item!");
            return;
        }
        $item = new RustID;
        $item->fullname = $itemArray['fullname'];
        $item->shortname = $itemArray['shortname'];
        $item->itemID = $itemArray['itemID'];
        $item->description = $itemArray['description'];
        $item->default_stack_size = $itemArray['default_stack_size'];
        $item->save();
        $this->line("Saved the item {$itemArray['shortname']} to the database!\n");
    }

    private function getSteamName(string $fullname)
    {
        $steamNames = [
            'MP5A4' => 'Mp5',
            'T-Shirt' => 'TShirt',
            'Bolt Action Rifle' => 'Bolt Rifle',
            'Burlap Trousers' => 'Burlap Pants',
            'Jacket' => 'Vagabond Jacket',
            'Longsleeve T-Shirt' => 'Long TShirt',
            'Bandana Mask' => 'Bandana',
            'LR-300 Assault Rifle' => 'LR300',
            'Python Revolver' => 'Python',
            'Road Sign Kilt' => 'Roadsign Pants',
            'Road Sign Jacket' => 'Roadsign Vest',
            'M39 Rifle' => 'M39',
            'Bone Helmet' => 'Deer Skull Mask',
            'Shirt' => 'Collared Shirt',
            'Rug Bear Skin' => 'Bearskin Rug',
            'Boots' => 'Work Boots',
            'Pickaxe' => '',
            'Improvised Balaclava' => 'Balaclava',
        ];
        if(array_key_exists($fullname, $steamNames)) {
            return $steamNames[$fullname];
        }
        return $fullname;
    }

    private function addCustomIDsToDatabase()
    {
        $customIDs = $this->getCustomIDs();
        foreach($customIDs as $customItem) {
            $item = RustID::where('fullname', $customItem['fullname'])->first();
            if($item != null) {
                $this->warn("Item {$customItem['fullname']} has already been added in the database! Skipping Item!");
                continue;
            }
            $item = new RustID;
            foreach($customItem as $key => $value) {
                $item->{$key} = $value;
            }
            $item->save();
            $this->line("Saved the item {$item->fullname} to the database!");
        }
    }

    private function getCustomIDs()
    {
        return [
            0 => [
                'fullname' => 'AK47 Skin',
                'shortname' => 'rifle.ak',
                'itemID' => '1545779598',
                'description' => 'High damage machine rifle.',
                'default_stack_size' => '1'
            ],
            1 => [
                'fullname' => 'AK47',
                'shortname' => 'rifle.ak',
                'itemID' => '1545779598',
                'description' => 'High damage machine rifle.',
                'default_stack_size' => '1'
            ],
            2 => [
                'fullname' => 'Hide Shirt',
                'shortname' => 'attire.hide.poncho',
                'itemID' => '980333378',
                'description' => 'A Poncho made from the hide of an animal.',
                'default_stack_size' => '1'
            ],
            3 => [
                'fullname' => 'Cap',
                'shortname' => 'hat.cap',
                'itemID' => '-1022661119',
                'description' => 'A baseball cap.',
                'default_stack_size' => '1'
            ],
            4 => [
                'fullname' => 'Pick Axe',
                'shortname' => 'pickaxe',
                'itemID' => '-1302129395',
                'description' => 'A Pickaxe, useful for gathering ore from rocks.',
                'default_stack_size' => '1'
            ],
            5 => [
                'fullname' => 'Hide Shoes',
                'shortname' => 'attire.hide.boots',
                'itemID' => '794356786',
                'description' => 'Boots made from the hide of an animal.',
                'default_stack_size' => '1'
            ],
            6 => [
                'fullname' => 'Stone Pick Axe',
                'shortname' => 'stone.pickaxe',
                'itemID' => '171931394',
                'description' => 'Primitive tool used for harvesting Stone, Metal ore and Sulfur ore.',
                'default_stack_size' => '1'
            ],
            7 => [
                'fullname' => 'Bandana Skin',
                'shortname' => 'mask.bandana',
                'itemID' => '-702051347',
                'description' => 'A square of cloth which is tied around the face over the nose and mouth.',
                'default_stack_size' => '1'
            ],
            8 => [
                'fullname' => 'TShirt Skin',
                'shortname' => 'tshirt',
                'itemID' => '223891266',
                'description' => 'A t-shirt with very short sleeves.',
                'default_stack_size' => '1'
            ],
            9 => [
                'fullname' => 'Rock Skin',
                'shortname' => 'rock',
                'itemID' => '963906841',
                'description' => 'A Rock. The most basic melee weapon and gathering tool.',
                'default_stack_size' => '1'
            ],
            10 => [
                'fullname' => 'Boots Skin',
                'shortname' => 'shoes.boots',
                'itemID' => '-1549739227',
                'description' => 'Work boots.',
                'default_stack_size' => '1'
            ],
            11 => [
                'fullname' => 'Hoodie Skin',
                'shortname' => 'hoodie',
                'itemID' => '1751045826',
                'description' => 'A hoodie.',
                'default_stack_size' => '1'
            ],
            12 => [
                'fullname' => 'Jacket Skin',
                'shortname' => 'jacket',
                'itemID' => '-1163532624',
                'description' => 'A rugged jacket.',
                'default_stack_size' => '1'
            ],
            13 => [
                'fullname' => 'Pants Skin',
                'shortname' => 'pants',
                'itemID' => '237239288',
                'description' => 'Pants.',
                'default_stack_size' => '1'
            ],
            14 => [
                'fullname' => 'Miner Hat',
                'shortname' => 'hat.miner',
                'itemID' => '-1539025626',
                'description' => 'A leather cap with a flashlight attached. It uses Low Grade Fuel and can be activated from the inventory.',
                'default_stack_size' => '1'
            ],
            15 => [
                'fullname' => 'Wooden Double Door',
                'shortname' => 'door.double.hinged.wood',
                'itemID' => '-1336109173',
                'description' => 'A Cheap door to secure your base. Its vulnerability to fire and weak explosive resistance makes the door a temporary solution to securing your base. Due to its flaws you should look at upgrading to a higher tier door such as Sheet Metal. The Wooden Door can take two kinds of locks the basic Key Lock and the Code Lock. To pick up the door, remove any locks and open, hold down the E (USE) key and select \'Pickup\'.',
                'default_stack_size' => '1'
            ]
        ];
    }
}
