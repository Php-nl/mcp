<?php

declare(strict_types=1);

// Outputs a non-JSON line to stdout, then exits without producing a valid response.
// Used to test that ServerProcess::receive() skips non-JSON lines and returns null on EOF.
fwrite(STDOUT, "not-json-output\n");
fflush(STDOUT);
