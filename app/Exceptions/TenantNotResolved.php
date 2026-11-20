<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when data would be written without knowing which tenant owns it,
 * or into a different tenant than the one in context. Failing loudly is
 * the point: silently picking a tenant is how data leaks between colleges.
 */
class TenantNotResolved extends RuntimeException {}
