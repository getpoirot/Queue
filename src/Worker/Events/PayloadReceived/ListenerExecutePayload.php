<?php
namespace Poirot\Queue\Worker\Events\PayloadReceived;

use Poirot\Events\Listener\aListener;


class ListenerExecutePayload
    extends aListener
{
    /**
     * Fire up action when event listener triggered
     *
     * [
     *   'ver'  => '0.1',
     *   'fun'  => print_r
     *   'args' => [
     *     ...
     *   ]
     * ]
     *
     * @param array $payload
     *
     * @return mixed
     */
    function __invoke($payload = null)
    {
        if (! $this->_isExecutableMessage($payload) )
            return;


        // We Can Consider Version

        if ( isset($payload['args']) && !empty($payload['args']) )
            call_user_func_array($payload['fun'], $payload['args']);
        else
            call_user_func($payload['fun']);
    }

    function _isExecutableMessage($payload)
    {
        return (is_array($payload) && isset($payload['fun']));
    }
}
