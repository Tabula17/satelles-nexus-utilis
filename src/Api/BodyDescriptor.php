<?php

namespace Tabula17\Satelles\Utilis\Api;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

/**
 * Represents a descriptor for a body, containing a description and an optional body model.
 *
 * Extends the AbstractDescriptor class to provide additional functionality for describing bodies
 * with a textual description and an optional model reference.
 *
 * @property string $description The textual description of the body.
 * @property AbstractDescriptor|null $bodyModel The optional descriptor model related to the body.
 *
 * @method __construct(string $description, ?AbstractDescriptor $bodyModel) Constructor accepting a description and an optional body model.
 */
class BodyDescriptor extends AbstractDescriptor
{
    protected(set) string $description;
    protected(set) ?AbstractDescriptor $bodyModel = null;

    /**
     * @param string $description
     * @param AbstractDescriptor|null $bodyModel
     */
    public function __construct(string $description, ?AbstractDescriptor $bodyModel)
    {
        $this->description = $description;
        $this->bodyModel = $bodyModel;
        parent::__construct();
    }

}