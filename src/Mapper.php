<?php
/**
 * @author Glenn Schmidt <glenn@codeacious.com>
 * @version $Id: Mapper.php 5383 2019-09-03 01:51:00Z glenn $
 */

namespace Codeacious\Mapper;

use Codeacious\Mapper\Exception\MappingException;
use Nocarrier\Hal as HalResource;
use Nocarrier\HalLink;
use DateTime;

class Mapper
{
    /**
     * @var array Array which maps domain model class names to serializable model class names.
     *    For example:
     * <pre>
     *     \App\User::class => \App\Api\V1\Models\User::class,
     * </pre>
     */
    private $classMap = [];

    /**
     * @var array Array which maps serializable model classes to URL route names that the
     *    UrlGenerator can interpret. The UrlGenerator should produce a URL referring to the
     *    canonical RESTful API endpoint for that type of model. For example:
     * <pre>
     *     \App\Api\V1\Models\User::class => 'api/v1/user',
     * </pre>
     */
    private $routeMap = [];

    /**
     * @var UrlGenerator The component that is responsible for turning a route name and parameters
     *    into an actual HTTP URL.
     */
    private $urlGenerator;

    /**
     * @var ModelFactory|null Optional component which produces serializable model objects from
     *    domain model objects. If set, this will be used in preference to the $classMap array.
     *    The Mapper will fall back to consulting the $classMap array if the factory returns null.
     */
    private $modelFactory = null;

    /**
     * @var array
     */
    private static $serializerRecursionStack = [];


    /**
     * @param UrlGenerator $urlGenerator
     */
    public function __construct(UrlGenerator $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @return array
     */
    public function getClassMap()
    {
        return $this->classMap;
    }

    /**
     * @param array $classMap
     * @return $this
     */
    public function setClassMap($classMap)
    {
        $this->classMap = $classMap;
        return $this;
    }

    /**
     * @return array
     */
    public function getRouteMap()
    {
        return $this->routeMap;
    }

    /**
     * @param array $routeMap
     * @return $this
     */
    public function setRouteMap($routeMap)
    {
        $this->routeMap = $routeMap;
        return $this;
    }

    /**
     * @return ModelFactory|null
     */
    public function getModelFactory()
    {
        return $this->modelFactory;
    }

    /**
     * @param ModelFactory|null $modelFactory
     * @return Mapper
     */
    public function setModelFactory($modelFactory)
    {
        $this->modelFactory = $modelFactory;
        return $this;
    }

    /**
     * @param object $object
     * @return object
     */
    public function map($object)
    {
        if (!is_object($object))
            throw new MappingException('Expected an object parameter');

        //If a factory has been configured, see if it is able to perform the mapping for us
        if ($this->modelFactory)
        {
            if (($factoryResult = $this->modelFactory->createModel($object)))
                return $factoryResult;
        }

        //Find the mapping configuration for this type of object
        $mappedClass = $this->getMappedClassForObject($object);
        if (empty($mappedClass))
            return $object;

        //Create an instance of the target class
        if (! ($object instanceof $mappedClass))
        {
            //The target class is expected to implement a constructor that takes a single parameter
            //(the domain model to convert)
            $object = new $mappedClass($object);
        }

        return $object;
    }

    /**
     * @param array|object $value
     * @return HalResource
     */
    public function createHalResource($value)
    {
        if (is_array($value))
            return new HalResource(null, $this->prepareDataArray($value));

        if (!is_object($value))
            throw new MappingException('Expected an array or object parameter');

        $value = $this->map($value);
        if ($value instanceof ModelHalProvider)
            $resource = $value->toHalResource($this);
        else
        {
            $array = $this->objectToArray($value);
            if ($array === null)
            {
                throw new MappingException('Unable to convert '.get_class($value)
                    .' object to an array');
            }

            $resource = new HalResource();
            foreach ($array as $k => $v)
            {
                if (is_object($v) && ($link = $this->objectToLink($value, $k, $v)))
                {
                    $resource->addHalLink($k, $link);
                    unset($array[$k]);
                }
                else if (is_array($v) && ($links = $this->arrayToLinks($value, $k, $v)))
                {
                    foreach ($links as $link)
                        $resource->addHalLink($k, $link, true);
                    unset($array[$k]);
                }
            }
            $resource->setData($this->prepareDataArray($array));
        }

        $resource->setUri($this->getUriForObject($value));

        return $resource;
    }

    /**
     * @param array $data
     * @return array
     */
    protected function prepareDataArray(array $data)
    {
        foreach ($data as $k => $v)
        {
            $obj = null;
            if (is_object($v))
            {
                $obj = $this->map($v);
                if (in_array($obj, self::$serializerRecursionStack, true))
                {
                    unset($data[$k]);
                    continue;
                }

                if ($v instanceof DateTime)
                    $v = $v->format(DateTime::RFC3339);
                else if (($array = $this->objectToArray($v)))
                    $v = $array;

                $data[$k] = $v;
            }

            if (is_array($v))
            {
                if ($obj)
                    self::$serializerRecursionStack[] = $obj;

                $data[$k] = $this->prepareDataArray($v);

                if ($obj)
                    array_pop(self::$serializerRecursionStack);
            }
        }

        return $data;
    }

    /**
     * @param object $object
     * @return string|null
     */
    protected function getMappedClassForObject($object)
    {
        $class = get_class($object);
        if (in_array($class, $this->classMap))
            return $class;

        if (array_key_exists($class, $this->classMap))
            return $this->classMap[$class];

        foreach ($this->classMap as $domainClass => $mappedClass)
        {
            if ($object instanceof $domainClass)
                return $mappedClass;
        }

        return null;
    }

    /**
     * @param object $object
     * @return string|null
     */
    protected function getUriForObject($object)
    {
        if ($object instanceof ModelUriProvider)
            return $object->getUri();

        $route = $this->getRouteForObject($object);
        if (empty($route))
            return null;

        $params = [];
        if ($object instanceof ModelRouteProvider)
            $params = $object->getRouteParameters();
        else if (($array = $this->objectToArray($object)))
            $params = $array;

        return $this->resolveRoute($route, $params);
    }

    /**
     * @param object $object
     * @return string|array|null
     */
    protected function getRouteForObject($object)
    {
        $class = get_class($object);
        if (array_key_exists($class, $this->routeMap))
            return $this->routeMap[$class];

        foreach ($this->routeMap as $class => $route)
        {
            if ($object instanceof $class)
                return $route;
        }

        return null;
    }

    /**
     * @param string|array $route
     * @param array $params
     * @return string
     */
    protected function resolveRoute($route, array $params = [])
    {
        if (is_array($route))
        {
            if (isset($route['params']) && is_array($route['params']))
                $params = $params + $route['params'];
            if (!isset($route['name']))
                throw new MappingException('Missing key "name" in route description array');
            $route = $route['name'];
        }

        if (!is_string($route))
            throw new MappingException('The route name must be a string');

        return $this->urlGenerator->route($route, $params, true);
    }

    /**
     * @param object $object
     * @return array|null
     */
    protected function objectToArray($object)
    {
        $array = null;
        if (is_callable([$object, 'getArrayCopy']))
        {
            $array = $object->getArrayCopy();
            if (!is_array($array))
                throw new MappingException('getArrayCopy() did not return an array');
        }
        else if (is_callable([$object, 'toArray']))
        {
            $array = $object->toArray();
            if (!is_array($array))
                throw new MappingException('toArray() did not return an array');
        }
        else if ($object instanceof \Traversable)
        {
            $array = [];
            foreach ($object as $key => $val)
                $array[$key] = $val;
        }

        return $array;
    }

    /**
     * @param object $parent
     * @param string $key
     * @param object $object
     * @return HalLink|null
     */
    protected function objectToLink($parent, $key, $object)
    {
        $uri = $this->getUriForObject($this->map($object));
        if (empty($uri))
            return null;

        $attrs = [];
        if ($parent instanceof ModelLinkAttributeProvider)
            $attrs = $parent->getLinkAttributes($key, $object);

        return new HalLink($uri, $attrs);
    }

    /**
     * Returns a collection of HalLinks, if and only if every element of $array is an object that
     * can be successfully mapped to a HalLink.
     *
     * @param object $parent
     * @param string $key
     * @param array $array
     * @return HalLink[]
     */
    protected function arrayToLinks($parent, $key, array $array)
    {
        $links = [];
        foreach ($array as $v)
        {
            if (is_object($v) && ($link = $this->objectToLink($parent, $key, $v)))
                $links[] = $link;
            else
                return [];
        }
        return $links;
    }
}