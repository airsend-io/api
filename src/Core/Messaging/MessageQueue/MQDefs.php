<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\MessageQueue;

class  MQDefs
{

    const PARALLEL_BACKGROUND = "as_parallel_bg_queue";
    const PARALLEL_BACKGROUND_LOW_PRIORITY = "as_parallel_bg_queue_low_priority";
    const SERIAL_BACKGROUND = "as_serial_bg_queue";
    const NODE_UTIL = "as_node_util_queue";

}

