<?php

namespace Alexusmai\LaravelFileManager\Traits;

trait EventCancellableTrait {
    /**
     * @var string Storeas the reason when event canceleld (handlerreturns false) 
     */
    public $cancellationReason = ''; 
}