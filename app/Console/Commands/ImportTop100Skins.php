<?php

namespace App\Console\Commands;

use App\Models\RustID;
use App\Models\RustSkin;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Symfony\Component\DomCrawler\Crawler;

class ImportTop100Skins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:top';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports Skins from the Steam Workshop!';
    /**
     * @var string
     */
    private $uri;
    /**
     * @var array
     */
    private $indexURLs;
    /**
     * @var string
     */
    private $sourceCode;
    private $itemURLs;
    /**
     * @var int
     */
    private $maxPage;
    private $startTime;
    /**
     * @var null
     */
    private $endTime;
    /**
     * @var array
     */
    private $rustIDArray;
    private $unableToLocate;
    /**
     * @var int
     */
    private $startPage;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->uri = "https://steamcommunity.com/";
        $this->indexURLs = [];
        $this->itemURLs = [];
        $this->startPage = 1;
        $this->maxPage = 1;
        $this->startTime = Carbon::now();
        $this->endTime = null;
        $this->rustIDArray = [];
        $this->unableToLocate = [];
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->getIndexURLs();
        $this->getItemURLs();
        $this->getItemInfo();

        $this->printUnableToLocateURLs();
        $this->printTimeInfo();
        $this->info("Last Start Page: {$this->startPage}");
        $this->info("Last Max Page: {$this->maxPage}");
    }

    private function getIndexURLs()
    {
        $this->line("Generating all Index URL's to search through...");
        $categories = $this->getCategories();

        foreach($categories as $category) {
            for ($i = $this->startPage; $i <= $this->maxPage; $i++) {
                $url = "https://steamcommunity.com/workshop/browse/?appid=252490&browsesort=trend&section=mtxitems&requiredtags%5B0%5D={$category}&actualsort=trend&p={$i}";
                array_push($this->indexURLs, $url);
            }
        }

        $this->info("Index URL's have been generated Successfully!");
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
            $this->info("Grabbed Source Code Successfully from: {$url}\n");
        } else {
            $this->alert("The url ({$url}) returned a status code of {$response->getStatusCode()}");
        }
        return $response->getStatusCode();
    }

    private function getItemURLs()
    {
        foreach($this->indexURLs as $url) {
            // Get the source code for each url
            if($this->getSourceCode($url) != 200) {
                $this->alert("This url {$url} returned a non-working status code. This must be the last page.");
                break;
            }
            // Create a new Symfony DOMCrawler and filter to only content in the body tag
            $crawler = new Crawler($this->sourceCode, $this->uri);
            $crawler = $crawler->filter('body');

            // Search Term for Crawler Filter (Each Item Link)
            $results = $crawler->filter('div.workshopBrowseItems div.workshopItem');

            // Loop through all search results and add URL's to array.
            $this->line("Searching {$url} source code for item data...");
            $results->each(function (Crawler $node, $i) {
                $itemURL = $node->filter('a.ugc')->link()->getUri();
                array_push($this->itemURLs, $itemURL);
            });
            $this->info("Grabbed all the data from the url {$url}\n");
        }
    }

    private function getItemInfo()
    {
        foreach($this->itemURLs as $url) {
            // Get the source code for each url
            if($this->getSourceCode($url) != 200) {
                $this->alert("This url {$url} returned a non-working status code. This must be the last page.");
                continue;
            }
            // Create a new Symfony DOMCrawler and filter to only content in the body tag
            $crawler = new Crawler($this->sourceCode, $this->uri);
            $error = $crawler->filter('head title')->text();
            if(strpos($error, "Error") === true) {
                $this->warn("Page resulted in a Steam Error Page. Skipping Item!");
                continue;
            }

            $crawler = $crawler->filter('body');

            try {
                $skinTitle = $crawler->filter('div.workshopItemTitle')->text();
            } catch (\InvalidArgumentException $e) {
                $this->warn("Can not find skin title, skipping this item");
                continue;
            }
            $this->info("FOUND: Skin Title - {$skinTitle}");

            $itemID = $this->getItemIDFromURL($url);
            $isInDatabase = $this->checkIfInDatabase($itemID);
            if($isInDatabase) {
                $this->warn("Skin is already in database, skipping this item...");
                continue;
            }

            $rustID = $this->getRustID($crawler);
            if($rustID == false) {
                $this->warn("Unable to find matching Rust ID in the database. Skipping this item!\n");
                array_push($this->unableToLocate, $url);
                continue;
            }

            $postedDate = $this->getPostedDate($crawler);

            $author = $this->getAuthor($crawler);

            $skinCommand = "skin add {$rustID->shortname} {$itemID}";
            $this->info("Generated Skin Command: {$skinCommand}");

            $this->line("Creating New Rust Skin Instance To Save To The Database...");
            // Add Skin To Database
            $rustSkin = new RustSkin;
            $rustSkin->name = $skinTitle;
            $rustSkin->skin_id = $itemID;
            $rustSkin->rust_id = $rustID->id;
            $rustSkin->date_added = $postedDate;
            $rustSkin->author = $author;
            $rustSkin->url = $url;
            $rustSkin->skin_command = $skinCommand;
            $rustSkin->save();

            $this->info("Saved the Skin {$skinTitle} to the database!\n");
        }
    }

    private function getItemIDFromURL($url)
    {
        $output_array = [];
        preg_match('/id=(\d{1,})/', $url, $output_array);
        $this->info("FOUND: Skin ID - {$output_array[1]}");
        return $output_array[1];
    }

    private function printTimeInfo()
    {
        $this->endTime = Carbon::now();
        $totalDuration = gmdate('H:i:s', $this->endTime->diffInSeconds($this->startTime));
        $this->line("\nStart time: {$this->startTime}");
        $this->line("Ending time: {$this->endTime}");
        $this->info("Completed command in: {$totalDuration}");
    }

    private function getRustID(Crawler $crawler)
    {
        $this->rustIDArray = [];
        $details = $crawler->filter('div.workshopTags');
        $details->each(function (Crawler $node, $i) {
            try {
                $detailType = $node->filter('span.workshopTagsTitle')->text();
                if(strpos($detailType, "Tags:") !== false) {
                    $this->line("Searching Tags within Skins Details Box");
                    $node->filter('a')->each(function (Crawler $tagNode, $i) {
                        $this->line("getRustID: Tag Value - {$tagNode->text()}");
                        array_push($this->rustIDArray, $this->findRustID($tagNode->text()));
                    });
                } else {
                    $value = $node->filter('a')->text();
                    $this->info("getRustID: A Tag Value - {$value}");
                    array_push($this->rustIDArray, $this->findRustID($value));
                }
            } catch (\InvalidArgumentException $e) {
                $this->warn('WARNING: Workshop tag title is missing.');
            }
        });

        foreach($this->rustIDArray as $ID) {
            if($ID != false || $ID != null) {
                $rustItem = RustID::where('id', $ID)->first();
                $this->info("FOUND: Rust Shortname - {$rustItem->shortname}");
                return $rustItem;
            }
        }

        return false;
    }

    private function findRustID(string $value)
    {
        $rustID = RustID::where('fullname', $value)->first();
        if($rustID != null) {
            return $rustID->id;
        }

        return false;
    }

    private function getPostedDate(Crawler $crawler)
    {
        $results = $crawler->filter('div.detailsStatRight');
        $nodeValues = $results->each(function (Crawler $node, $i) {
            return $node->text();
        });

        foreach($nodeValues as $value) {
            if(strpos($value, "@")) {
                $this->info("FOUND: Posted Date - {$value}");
                return $value;
            }
        }
    }

    private function getAuthor(Crawler $crawler)
    {
        $author = $crawler->filter('div.creatorsBlock div.friendBlock a')->link()->getUri();
        $this->info("FOUND: Skin Author - {$author}");
        return $author;
    }

    private function printUnableToLocateURLs()
    {
        $this->warn("Unable to locate the following items:\n");
        foreach($this->unableToLocate as $url) {
            $this->line("{$url}");
        }
    }

    private function checkIfInDatabase($itemID)
    {
        $skin = RustSkin::where('skin_id', $itemID)->first();
        if($skin != null) {
            return true;
        }
        return false;
    }

    private function getCategories()
    {
        return [
            'Bandana',
            'Balaclava',
            'Beenie+Hat',
            'Burlap+Shoes',
            'Burlap+Shirt',
            'Burlap+Pants',
            'Burlap+Headwrap',
            'Bucket+Helmet',
            'Boonie+Hat',
            'Cap',
            'Collared+Shirt',
            'Coffee+Can+Helmet',
            'Deer+Skull+Mask',
            'Hide+Skirt',
            'Hide+Shirt',
            'Hide+Pants',
            'Hide+Shoes',
            'Hide+Halterneck',
            'Hoodie',
            'Hide+Poncho',
            'Leather+Gloves',
            'Long+TShirt',
            'Metal+Chest+Plate',
            'Metal+Facemask',
            'Miner+Hat',
            'Pants',
            'Roadsign+Vest',
            'Roadsign+Pants',
            'Riot+Helmet',
            'Snow+Jacket',
            'Shorts',
            'Tank+Top',
            'TShirt',
            'Vagabond+Jacket',
            'Work+Boots',
            'AK47',
            'Bolt+Rifle',
            'Bone+Club',
            'Bone+Knife',
            'Crossbow',
            'Double+Barrel+Shotgun',
            'Eoka+Pistol',
            'F1+Grenade',
            'Longsword',
            'Mp5',
            'Pump+Shotgun',
            'Rock',
            'Salvaged+Hammer',
            'Salvaged+Icepick',
            'Satchel+Charge',
            'Semi-Automatic+Pistol',
            'Stone+Hatchet',
            'Stone+Pick+Axe',
            'Sword',
            'Thompson',
            'Hammer',
            'Hatchet',
            'Pick+Axe',
            'Revolver',
            'Rocket+Launcher',
            'Semi-Automatic+Rifle',
            'Waterpipe+Shotgun',
            'Custom+SMG',
            'Python',
            'LR300',
            'Combat+Knife',
            'Armored+Door',
            'Concrete+Barricade',
            'Large+Wood+Box',
            'Reactive+Target',
            'Sandbag+Barricade',
            'Sleeping+Bag',
            'Sheet+Metal+Door',
            'Water+Purifier',
            'Wood+Storage+Box',
            'Wooden+Door',
            'Wooden+Double+Door',
            'Sheet+Metal+Double+Door',
            'Armored+Double+Door'
        ];
    }
}
