<?php
declare(strict_types=1);

namespace SiteForge;

interface Generator
{
    /** Short machine name stored on each version: 'claude' or 'local'. */
    public function id(): string;

    /** Human-readable name for the UI. */
    public function label(): string;

    /**
     * Build a site from scratch.
     *
     * @param array{brief:string,site_type:string,name:string,images:array,progress:callable} $ctx
     */
    public function create(array $ctx): Result;

    /**
     * Apply a plain-English change to an existing site.
     *
     * @param array{instruction:string,site_type:string,name:string,files:array,spec:?array,images:array,progress:callable} $ctx
     */
    public function edit(array $ctx): Result;
}
