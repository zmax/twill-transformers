<?php

namespace A17\TwillTransformers\Transformers;

use Illuminate\Support\Str;
use A17\TwillTransformers\Transformer;
use A17\TwillTransformers\Exceptions\Block as BlockException;

class Block extends Transformer
{
    /**
     * @var array
     */
    public $__browsers = [];

    /**
     * @var array
     */
    public $__blocks = [];

    /**
     * @var array
     */
    public $internalVars = ['__browsers', '__blocks'];

    /**
     * @var string
     */
    protected $type;

    /**
     * Block constructor.
     *
     * @param null $data
     */
    public function __construct($data = null)
    {
        $this->__browsers = collect();

        $this->__blocks = collect();

        parent::__construct($data);
    }

    /**
     * @return array|\Illuminate\Support\Collection
     */
    public function getBlocks()
    {
        if ($this->__blocks->count() > 0) {
            return $this->__blocks;
        }

        if (isset($this->data->__blocks)) {
            return $this->data->__blocks;
        }

        return collect();
    }

    /**
     * @param array|\Illuminate\Support\Collection $blocks
     */
    public function setBlocks($blocks): void
    {
        $this->__blocks = $blocks;
    }

    /**
     * @return array|\Illuminate\Support\Collection
     */
    public function getBrowsers()
    {
        if ($this->__browsers->count() > 0) {
            return $this->__browsers;
        }

        if (isset($this->data->__browsers)) {
            return $this->data->__browsers;
        }

        return collect();
    }

    /**
     * @param array|\Illuminate\Support\Collection $browsers
     */
    public function setBrowsers($browsers): void
    {
        $this->__browsers = $browsers;
    }

    /**
     * @return array|null
     * @throws \A17\TwillTransformers\Exceptions\Block
     */
    protected function transformBlock()
    {
        if (filled($transformer = $this->findBlockTransformer($this))) {
            return $this->transformAndAddType($transformer);
        }

        if (filled($raw = $this->transformBlockRaw())) {
            return $raw;
        }

        BlockException::classNotFound($this->block->type);
    }

    /**
     * @param null $block
     * @return \A17\TwillTransformers\Transformer|null
     */
    public function findBlockTransformer($block = null)
    {
        $block ??= $this;

        if (blank($block->type)) {
            throw new \Exception('Block is missing type');
        }

        $transformer = $this->findTransformerByMethodName(
            'transformBlock' . Str::studly($block->type),
        );

        if (blank($transformer)) {
            return null;
        }

        return $this->transformerSetDataOrTransmorph($transformer, $block);
    }

    /**
     * @return array|\Illuminate\Support\Collection|null
     * @throws \A17\TwillTransformers\Exceptions\Block
     */
    public function transform()
    {
        if ($result = $this->transformBlockCollection()) {
            return $result->filter();
        }

        return $this->transformGenericBlock();
    }

    /**
     * @param $blocks
     */
    public function pushBlocks($blocks)
    {
        collect($blocks)->each(function ($block) {
            $this->__blocks->push($block);
        });
    }

    protected function transformBlockCollection()
    {
        if (!$this->isBlockCollection($collection = $this->data)) {
            return null;
        }

        return collect($collection)->map(function ($item) {
            return $item instanceof Block
                ? $item->transform()
                : (new self($item))->setActiveLocale($this)->transform();
        });
    }

    protected function transformGenericBlock()
    {
        return $this->transformBlock() ?? null;
    }

    protected function transformAndAddType($transformer)
    {
        // This code must be ran before everything
        $transformed = $transformer->transform();

        if (is_null($transformed)) {
            return null;
        }

        // Because the type of the block may change during transform()
        return ['type' => $transformer->type ?? null] + ($transformed ?? []);
    }

    protected function setBlockType($data)
    {
        $type = $this->isBlockCollection($data)
            ? 'block-collection'
            : (is_string($data)
                ? $data
                : $data['type'] ?? ($data->type ?? null));

        if (filled($this->type = $this->type ?? $type)) {
            return;
        }

        $this->type = Str::snake(Str::afterLast(get_class($this), '\\'));

        if ($this->type === 'block') {
            throw new \Exception(
                'Data for block must contain a type. Could not infer type.',
            );
        }
    }

    public function __get($name)
    {
        if ($name === 'blocks') {
            $blocks = $this->__blocks ?? collect();

            if (blank($blocks)) {
                $data = $this->getData();

                $blocks = $data->blocks;
            }

            return $blocks;
        }

        return parent::__get($name); // TODO: Change the autogenerated stub
    }

    public function getInternalVars()
    {
        $vars = [];

        foreach ($this->internalVars as $var) {
            $vars[$var] = $this->$var;

            $vars[Str::after($var, '__')] = $this->$var;
        }

        return $vars;
    }
}
