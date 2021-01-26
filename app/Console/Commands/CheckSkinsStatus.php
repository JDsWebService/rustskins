<?php

namespace App\Console\Commands;

use App\Models\RustSkin;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class CheckSkinsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'skins:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks the skins workshop page to see if it returns any errors.';
    /**
     * @var string
     */
    private $uri;
    /**
     * @var null
     */
    private $minRange;
    /**
     * @var null
     */
    private $maxRange;
    /**
     * @var Carbon
     */
    private $startTime;
    /**
     * @var null
     */
    private $endTime;
    /**
     * @var string
     */
    private $sourceCode;
    private $maxRangeInDatabase;
    private $url;
    /**
     * @var array
     */
    private $errors;
    /**
     * @var string
     */
    private $formattedJson;
    /**
     * @var int
     */
    private $titleErrorCount;
    /**
     * @var int
     */
    private $appNameErrorCount;
    /**
     * @var mixed
     */
    private $currentSkin;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->uri = "https://steamcommunity.com/";
        $this->minRange = null;
        $this->maxRange = null;
        $this->startTime = Carbon::now();
        $this->endTime = null;
        $this->errors = [];
        $this->titleErrorCount = 0;
        $this->appNameErrorCount = 0;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->getMaxRangeInDatabase();
        $this->askUserQuestions();
        $this->validateUserQuestions();

        $skins = RustSkin::where('id', '>=', $this->minRange)->where('id', '<=', $this->maxRange)->get();
        foreach($skins as $skin) {
            $this->currentSkin = $skin;
            $this->line("\nWorking on ID: {$skin->id}");
            $this->url = $skin->url;
            $this->line("Working on URL: {$this->url}");
            $this->getSourceCode($this->url);
            $this->checkIfErrorExists();
        }

        $this->writeErrorsToFile();

        $this->printErrorInfo();
        $this->printTimeInfo();
        $this->info("Last Min Range: {$this->minRange}");
        $this->info("Last Max Range: {$this->maxRange}");
    }

    private function printTimeInfo()
    {
        $this->endTime = Carbon::now();
        $totalDuration = gmdate('H:i:s', $this->endTime->diffInSeconds($this->startTime));
        $this->line("\nStart time: {$this->startTime}");
        $this->line("Ending time: {$this->endTime}");
        $this->info("Completed command in: {$totalDuration}");
    }

    private function askUserQuestions()
    {
        $this->minRange = $this->ask("What is the minimum ID number you want to check?");
        $this->maxRange = $this->ask("What is the maximum ID number you want to check?");
    }

    private function validateUserQuestions()
    {
        // Check Max Range
        if($this->maxRange > $this->maxRangeInDatabase) {
            throw new \Exception("Max range can not be higher then the number of entries in the database.");
        }

        // Check Min Range
        if($this->minRange < 1) {
            throw new \Exception("Min range can not be lower then 1");
        }

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

    private function checkIfErrorExists()
    {
        // Create a new Symfony DOMCrawler and filter to only content in the body tag
        $crawler = new Crawler($this->sourceCode, $this->uri);

        try {
            // Check the title of the page
            $titleError = $this->checkPageTitleForError($crawler);
            // Check the Game App Name
            $appNameError = $this->checkPageAppNameForError($crawler);
        } catch(\Exception $e) {
            $this->alert($e->getMessage());
            return true;
        }

        $this->info("Skin page is not returning any errors! Moving on...");
    }

    private function getMaxRangeInDatabase()
    {
        $lastSkin = RustSkin::orderBy('id', 'desc')->first();
        $this->maxRangeInDatabase = $lastSkin->id;
        $this->info("Max Range ID In Database: {$this->maxRangeInDatabase}");
    }

    private function checkPageTitleForError(Crawler $crawler)
    {
        $crawler = $crawler->filter('head');

        $title = $crawler->filter('title')->text();
        $this->line("Title For Page Is: {$title}");

        $this->line("Converting Title to Lower Case");
        $title = Str::lower($title);
        $this->line("Title is now converted to: {$title}");
        if(strpos($title, "steam community :: error") !== false) {
            $titleError = [
                'skinID' => $this->currentSkin->id,
                'Page Title' => $title,
                'URL' => $this->url,
                'Error Type' => 'title'
            ];
            array_push($this->errors, $titleError);
            $this->titleErrorCount += 1;
            throw new \Exception("Title contains keyword 'error'");
        }
        $this->info("Page Title is Valid!");
    }

    private function checkPageAppNameForError(Crawler $crawler)
    {
        $crawler = $crawler->filter('body');

        $appName = $crawler->filter('div.apphub_HeaderTop div.apphub_AppName')->text();
        $this->line("App Name For Page Is: {$appName}");

        $this->line("Converting App Name to Lower Case");
        $appName = Str::lower($appName);
        $this->line("App Name is now converted to: {$appName}");

        if(strpos($appName, "rust") === false) {
            $appNameError = [
                'skinID' => $this->currentSkin->id,
                'Page Title' => $appName,
                'URL' => $this->url,
                'Error Type' => 'appName'
            ];
            array_push($this->errors, $appNameError);
            $this->appNameErrorCount += 1;
            throw new \Exception("App name does not contain keyword 'rust'");
        }
        $this->info("App Name is Valid!");
    }

    private function writeErrorsToFile()
    {
        $this->line("\nConverting Errors To JSON");
        $this->formattedJson = stripslashes(json_encode($this->errors, JSON_PRETTY_PRINT));
        $this->info("Formatting Complete!");
        $this->line("Saving Formatted JSON To File");
        file_put_contents(base_path('storage/app/public/errors.json'), stripslashes($this->formattedJson));
        $this->info("File Saved!");
    }

    private function printErrorInfo()
    {
        if($this->titleErrorCount != 0 || $this->appNameErrorCount != 0) {
            $this->alert("Errors were found while running skins check");
            $this->info("Title Errors: {$this->titleErrorCount}");
            $this->info("App Name Errors: {$this->appNameErrorCount}");
        }
    }
}
