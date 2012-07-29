<?php
/**
 * Printo
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Printo;

require_once __DIR__ . '/debuglib/debuglib.php';

use DbugL;
use Exception;
use RuntimeException;

/**
 * Printo
 *
 * @package Printo
 * 
 * @author Akihito Koriyama (@koriym)
 */
class Printo
{
    /**
     * Loadeed class
     * 
     * @var array
     */
    private $classes = [];

    /**
     * @param object $object
     */
    public function __construct($object)
    {
        if (! is_object($object)) {
            throw new RuntimeException('Object only: ' . gettype($object));
        }
        $this->object = $object;
    }

    /**
     *
     * @param object $object
     *
     * @return string
     */
    public function makeData($object)
    {
        $data = [];
        $props = (new \ReflectionObject($object))->getProperties();
        foreach ($props as $prop) {
            $prop->setAccessible(true);
            $value = $prop->getValue($object);
            $name = $prop->name;
            $class = gettype($prop->getValue($object));
            if (is_object($value)) {
                $class = get_class($value);
                $loaded = in_array($class,  $this->classes);
                if ($loaded) {
                    $data["@{$name}"] = ["@{$name}", $value];
                } else {
                    $this->classes[] = $class;
                    $child = $this->makeData($value);
                    $hasChild = ($child !== []);
                    $data["({$class}) {$name}"] = $hasChild ? [$child, $value] : [$name, $value];
                }
            } else {
                $value = $prop->getValue($object);
                $data[$name] = ["($class) {$name}", $value];
            }
        }
        return $data;
    }

    private function makeString(array $data)
    {
        $li = '';
        foreach ($data as $key => $val) {
            list($element, $value) = $val;
            $varId = md5(print_r($element, true));
            $this->vars[$varId] = $value;
            if (is_array($element)) {
                //                $open = "<li><a href=\"#\" id=\"{$varId}\">{$key}</a>";
                $open = "<li id=\"{$varId}\">{$key}";
                $list = '<ul>' . $this->makeString($element) . '</ul>';
                $close = '</li>';
                $li .= $open . $list . $close;
            } else {
                //$li .= "<li><a href=\"#\" id=\"{$varId}\">{$element}</a></li>\n";
                $li .= "<li id=\"{$varId}\">{$element}\n";
            }
        }
        return $li;
    }

    /**
     * Store variable representation using 'print_a'
     *
     * @return string
     */
    private function getVarDivs()
    {
        $div = '';
        foreach ($this->vars as $id => $var) {
            $varView = print_a($var, 'return:1;');
            $div .= "<div id=\"data_{$id}\">$varView</div>";
        }
        return $div;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            $rootName = get_class($this->object);
            $data = $this->makeData($this->object);
            $list = $this->makeString($data);
            $vars = $this->getVarDivs();
            // object list
            $list = "<li>{$rootName}<ul>{$list}</ul></li>";
            // vars
            $list .= "<span style=\"visibility:hidden;\">{$vars}</span>";
            $html = require __DIR__ . '/html.php';
            return $html;
        } catch (Exception $e) {
            error_log($e);
        }
    }
}