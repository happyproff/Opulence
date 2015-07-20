<?php
/**
 * Copyright (C) 2015 David Young
 *
 * Defines the template factory
 */
namespace Opulence\Views\Factories;
use Opulence\Files\FileSystem;
use Opulence\Views\IBuilder;
use Opulence\Views\ITemplate;
use Opulence\Views\Template;

class TemplateFactory implements ITemplateFactory
{
    /** @var FileSystem The file system to read templates with */
    private $fileSystem = null;
    /** @var string The root directory of the templates */
    private $rootTemplateDirectory = "";
    /** @var array The mapping of template paths to a list of builders to run whenever the template is created */
    private $builders = [];
    /** @var array The mapping of aliases to their template paths */
    private $aliases = [];

    /**
     * @param FileSystem $fileSystem The file system to read templates with
     * @param string|null $rootTemplateDirectory The root directory of the templates if it's known, otherwise null
     */
    public function __construct(FileSystem $fileSystem, $rootTemplateDirectory = null)
    {
        $this->fileSystem = $fileSystem;

        if($rootTemplateDirectory !== null)
        {
            $this->setRootTemplateDirectory($rootTemplateDirectory);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function alias($alias, $templatePath)
    {
        $this->aliases[$alias] = $templatePath;
    }

    /**
     * {@inheritdoc}
     */
    public function create($name)
    {
        $isAlias = $this->isAlias($name);
        $templatePath = $name;

        if($isAlias)
        {
            $templatePath = $this->aliases[$name];
        }

        $templatePath = ltrim($templatePath, "/");
        $content = $this->fileSystem->read($this->rootTemplateDirectory . "/" . $templatePath);
        $template = new Template($content);
        $template = $this->runBuilders($templatePath, $template);

        if($isAlias)
        {
            // Run any builders registered to the alias
            $template = $this->runBuilders($name, $template);
        }

        return $template;
    }

    /**
     * {@inheritdoc}
     */
    public function registerBuilder($names, callable $callback)
    {
        foreach((array)$names as $name)
        {
            if(!isset($this->builders[$name]))
            {
                $this->builders[$name] = [];
            }

            $this->builders[$name][] = $callback;
        }
    }

    /**
     * @param string $rootTemplateDirectory
     */
    public function setRootTemplateDirectory($rootTemplateDirectory)
    {
        $this->rootTemplateDirectory = rtrim($rootTemplateDirectory, "/");
    }

    /**
     * Gets whether or not something is an alias to a template path
     *
     * @param string $name The item to check
     * @return bool True if the input is an alias, otherwise false
     */
    private function isAlias($name)
    {
        return isset($this->aliases[$name]);
    }

    /**
     * Runs the builders for a template (if there any)
     *
     * @param string $templatePath The path of the template relative to the root template directory
     * @param ITemplate $template The template to run builders on
     * @return ITemplate The built template
     */
    private function runBuilders($templatePath, ITemplate $template)
    {
        if(isset($this->builders[$templatePath]))
        {
            foreach($this->builders[$templatePath] as $callback)
            {
                /** @var IBuilder $builder */
                $builder = $callback();
                $template = $builder->build($template);
            }
        }

        return $template;
    }
}