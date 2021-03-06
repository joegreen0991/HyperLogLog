<?php
include __DIR__ . '/../vendor/autoload.php';

ini_set('memory_limit','512M');

function testHeader($title)
{
    echo '-----------------------------------------------------'.PHP_EOL;
    echo $title . PHP_EOL;
    echo '-----------------------------------------------------'.PHP_EOL;
    echo '|'.implode('|',map_pad(array('Actual','Estimated','Total','Error'))).'|' . PHP_EOL;
    echo '-----------------------------------------------------'.PHP_EOL;
}

function errorLine($actual, $estimated)
{
    if($actual == 0)
    {
        return '-';
    }

    return number_format((($actual - $estimated) / $actual) * 100, 4) . '%';
}

function map_pad($array)
{
    return array_map(function($val){return str_pad($val, 12, ' ', STR_PAD_BOTH);}, $array);
}

function printResults($resultsArray)
{
    foreach($resultsArray as $results)
    {
        $results[] = errorLine($results[0], $results[1]);

        echo implode(" " , map_pad($results));

        echo PHP_EOL;
    }

}

function fileResults($file, $resultsArray)
{
    foreach($resultsArray as $results)
    {
        file_put_contents($file, implode("\t" , $results) . PHP_EOL, FILE_APPEND);
    }
}

$testMin = 1;
$testMax = 100000;
$tests = 50;
$print = true;
$verbose = false;
$filename = __DIR__ . '/data/minhash/results.'.date('Y-m-d_h-i-s').'.csv';



file_put_contents($filename,'');

for($i = $testMin; $i <= $testMax; $i += $block)
{
    $block = pow(10, max(0,floor(log10($i))));

    $test = new Test($i);

    $test->test($tests);

    if($print)
    {
        if($verbose) {
            testHeader('Tested: ' . $i);
            printResults($test->results());
        }
        testHeader('Average: ' . $i);
        printResults(array($test->averages()));
    }

    fileResults($filename, $test->results());

    echo "Tested $i\r";
}

echo PHP_EOL;



class Test {

    private $i;

    private $average = array(0,0,0);

    private $results = array();

    public function __construct($i)
    {
        $this->i = $i;
    }

    private function random()
    {
        $start = 100000000;

        return mt_rand($start, $start + 2 * $this->i);
    }

    public function test($repeat = 100)
    {
        while($repeat--)
        {
            $keep1 = array();
            $keep2 = array();

            $ll1 = new HyperLogLog\MinHash();
            $ll2 = new HyperLogLog\MinHash();

            $total = 0;

            while(1)
            {
                $total++;

                $rand = $this->random();

                $keep1[$rand] = 1;

                $ll1->add($rand);

                $rand = $this->random();

                $keep2[$rand] = 1;

                $ll2->add($rand);

                if(($count = count($keep2)) >= $this->i)
                {
                    break;
                }
            }

            $intersection = \HyperLogLog\Utils\MinHashIntersector::count(array($ll1,$ll2));

            $actual = count(array_intersect_key($keep1,$keep2));

            if($actual == 0 || $intersection == 0)
            {
                continue;
            }

            $ll1->union($ll2);

            $total = $ll1->count();

            $this->average[0] += $actual;

            $this->average[1] += $intersection;

            $this->average[2] += $total;

            $this->results[] = array($actual, $intersection, $total);
        }
    }

    public function averages()
    {
        return $this->average;
    }

    public function results()
    {
        return $this->results;
    }
}