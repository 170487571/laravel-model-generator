<?php namespace Iber\Generator\Commands;

use Iber\Generator\Utilities\RuleProcessor;
use Iber\Generator\Utilities\VariableConversion;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Console\GeneratorCommand;

class MakeModelsCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:models';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build models from existing schema.';

    /**
     * Default model namespace.
     *
     * @var string
     */
    protected $namespace = 'Models/';

    /**
     * Default class the model extends.
     *
     * @var string
     */
    protected $extends = 'Model';

    /**
     * Rule processor class instance.
     *
     * @var
     */
    protected $ruleProcessor;

    /**
     * Rules for columns that go into the guarded list.
     *
     * @var array
     */
    protected $guardedRules = 'ends:_guarded'; //['ends' => ['_id', 'ids'], 'equals' => ['id']];

    /**
     * Rules for columns that go into the fillable list.
     *
     * @var array
     */
    protected $fillableRules = '';

    /**
     * Rules for columns that set whether the timestamps property is set to true/false.
     *
     * @var array
     */
    protected $timestampRules = 'ends:_at'; //['ends' => ['_at']];

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $this->ruleProcessor = new RuleProcessor();

        $tables = $this->getSchemaTables();

        foreach ($tables as $table) {
            $this->generateTable($table->name);
        }
    }

    /**
     * Get schema tables.
     *
     * @return array
     */
    protected function getSchemaTables()
    {
        $tables = \DB::select("SELECT table_name AS `name` FROM information_schema.tables WHERE table_schema = DATABASE()");

        return $tables;
    }

    /**
     * Generate a model file from a database table.
     *
     * @param $table
     */
    protected function generateTable($table)
    {
        //prefix is the sub-directory within app
        $prefix = $this->option('dir');

        $class = VariableConversion::convertTableNameToClassName($table);

        $name = rtrim($this->parseName($prefix . $class), 's');

        if ($this->files->exists($path = $this->getPath($name))) {
            return $this->error($this->extends . ' for '.$table.' already exists!');
        }

        $this->makeDirectory($path);

        $this->files->put($path, $this->replaceTokens($name, $table));

        $this->info($this->extends . ' for '.$table.' created successfully.');
    }

    /**
     * Replace all stub tokens with properties.
     *
     * @param $name
     * @param $table
     *
     * @return mixed|string
     */
    protected function replaceTokens($name, $table)
    {
        $class = $this->buildClass($name);

        $properties = $this->getTableProperties($table);

        $class = str_replace('{{extends}}', $this->option('extends'), $class);
        $class = str_replace('{{fillable}}', 'protected $fillable = ' . VariableConversion::convertArrayToString($properties['fillable']) . ';', $class);
        $class = str_replace('{{guarded}}', 'protected $guarded = ' . VariableConversion::convertArrayToString($properties['guarded']) . ';', $class);
        $class = str_replace('{{timestamps}}', 'public $timestamps = ' . VariableConversion::convertBooleanToString($properties['timestamps']) . ';', $class);

        return $class;
    }

    /**
     * Fill up $fillable/$guarded/$timestamps properties based on table columns.
     *
     * @param $table
     *
     * @return array
     */
    protected function getTableProperties($table)
    {
        $fillable = [];
        $guarded = [];
        $timestamps = false;

        $columns = $this->getTableColumns($table);

        foreach ($columns as $column) {

            //priotitze guarded properties and move to fillable
            if ($this->ruleProcessor->check($this->option('fillable'), $column->name)) {
                if(!in_array($column->name, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                    $fillable[] = $column->name;
                }
            }
            if ($this->ruleProcessor->check($this->option('guarded'), $column->name)) {
                $fillable[] = $column->name;
            }
            //check if this model is timestampable
            if ($this->ruleProcessor->check($this->option('timestamps'), $column->name)) {
                $timestamps = true;
            }
        }

        return ['fillable' => $fillable, 'guarded' => $guarded, 'timestamps' => $timestamps];
    }

    /**
     * Get table columns.
     *
     * @param $table
     *
     * @return array
     */
    protected function getTableColumns($table)
    {
        $columns = \DB::select("SELECT COLUMN_NAME as `name` FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'");

        return $columns;
    }

    /**
     * Get stub file location.
     *
     * @return string
     */
    public function getStub()
    {
        return __DIR__ . '/../stubs/model.stub';
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['dir', null, InputOption::VALUE_OPTIONAL, 'Model directory', $this->namespace],
            ['extends', null, InputOption::VALUE_OPTIONAL, 'Parent class', $this->extends],
            ['fillable', null, InputOption::VALUE_OPTIONAL, 'Rules for $fillable array columns', $this->fillableRules],
            ['guarded', null, InputOption::VALUE_OPTIONAL, 'Rules for $guarded array columns', $this->guardedRules],
            ['timestamps', null, InputOption::VALUE_OPTIONAL, 'Rules for $timestamps columns', $this->timestampRules],
        ];
    }
}
