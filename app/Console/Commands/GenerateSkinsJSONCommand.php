<?php

namespace App\Console\Commands;

use App\Models\RustID;
use App\Models\RustSkin;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateSkinsJSONCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'skins:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates a JSON file of all the skins in the database.';
    private $template;
    /**
     * @var false|string
     */
    private $formattedJson;
    /**
     * @var Carbon
     */
    private $endTime;
    private $startTime;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->startTime = Carbon::now();
        $this->endTime = null;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->generateTemplate();
        $this->getSkinsArrayByShortName();
        $this->convertTemplateToJson();
        $this->saveFormattedJsonToFile();
        $this->printTimeInfo();

        return 0;
    }

    private function generateTemplate()
    {
        $this->line("Generating Blank Template");
        $this->template = [
            "Command" => "skin",
            "Skins" => [

            ],
            "Container Panel Name" => "generic",
            "Container Capacity" => 36,
            "UI" => [
                "Background Color" => "0.18 0.28 0.36",
                "Background Anchors" => [
                    "Anchor Min X" => "1.0",
                    "Anchor Min Y" => "1.0",
                    "Anchor Max X" => "1.0",
                    "Anchor Max Y" => "1.0"
                ],
                "Background Offsets" => [
                    "Offset Min X" => "-300",
                    "Offset Min Y" => "-100",
                    "Offset Max X" => "0",
                    "Offset Max Y" => "0"
                ],
                "Left Button Text" => "<size=36><</size>",
                "Left Button Color" => "0.11 0.51 0.83",
                "Left Button Anchors" => [
                    "Anchor Min X" => "0.025",
                    "Anchor Min Y" => "0.05",
                    "Anchor Max X" => "0.325",
                    "Anchor Max Y" => "0.95"
                ],
                "Center Button Text" => "<size=36>Page: {page}</size>",
                "Center Button Color" => "0.11 0.51 0.83",
                "Center Button Anchors" => [
                    "Anchor Min X" => "0.350",
                    "Anchor Min Y" => "0.05",
                    "Anchor Max X" => "0.650",
                    "Anchor Max Y" => "0.95"
                ],
                "Right Button Text" => "<size=36>></size>",
                "Right Button Color" => "0.11 0.51 0.83",
                "Right Button Anchors" => [
                    "Anchor Min X" => "0.675",
                    "Anchor Min Y" => "0.05",
                    "Anchor Max X" => "0.975",
                    "Anchor Max Y" => "0.95"
                ]
            ],
            "Debug" => false
        ];
        $this->info("Blank Template Generated Successfully!");
    }

    private function getSkinsArrayByShortName()
    {
        $groupIDArray = [];


        $this->line("Grabbing unique shortnames from database");
        $groups = RustID::orderBy('shortname','desc')->get()->groupBy("shortname");
        $this->info("Grabbed unique shortnames from database successfully!");

        // Loop Through Each of the Groups
        $this->line("Looping through all the groups and grabbing shortname and ID values");
        foreach($groups as $group) {
            foreach($group as $entry) {
                $groupIDArray[$entry->shortname] = $entry->id;
            }
        }
        $this->info("GroupIDArray has been successfully generated!");

        // Loop through all the groupIDArray
        $this->line("Looping through all the shortnames...\n");
        foreach($groupIDArray as $shortname => $id) {
            $skinsArray = [];
            $this->line("\nWorking on shortname: {$shortname}");
            $this->line("Grabbing all skins for {$shortname} from the database...");
            $skins = RustSkin::where('rust_id', $id)->get();
            $this->info("Grabbed skins successfully from database!");
            if($skins->count() != 0) {
                $this->info("Found ({$skins->count()}) for: {$shortname}");
                $skinsArray["Item Shortname"] = $shortname;
                $skinsArray["Skins"] = [0];
                $this->line("Looping Through Each Skin and Adding To Array");
                foreach($skins as $skin) {
                    array_push($skinsArray["Skins"], intval($skin->skin_id));
                }
                $this->info("Looping complete!");
                $this->line("Adding Array To Template");
                array_push($this->template["Skins"], $skinsArray);
                $this->info("Array Added To Template Successfully!");
                //break; // Delete this line to enable full database scan
            } else {
                $this->warn("No skins found for {$shortname}. Skipping this group!");
                continue;
            }
        }

    }

    private function convertTemplateToJson()
    {
        $this->line("Converting Template & Populated Array To JSON");
        $this->formattedJson = stripslashes(json_encode($this->template, JSON_PRETTY_PRINT));
        $this->info("Formatting Complete!");
    }

    private function saveFormattedJsonToFile()
    {
        $this->line("Saving Formatted JSON To File");
        file_put_contents(base_path('storage/app/public/Skins.json'), stripslashes($this->formattedJson));
        $this->info("File Saved!");
    }

    private function printTimeInfo()
    {
        $this->endTime = Carbon::now();
        $totalDuration = gmdate('H:i:s', $this->endTime->diffInSeconds($this->startTime));
        $this->line("\nStart time: {$this->startTime}");
        $this->line("Ending time: {$this->endTime}");
        $this->info("Completed command in: {$totalDuration}");
    }
}
