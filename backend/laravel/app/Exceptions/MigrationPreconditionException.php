<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a migration cannot proceed because existing data violates the
 * invariant the migration would enforce (or would be lost on rollback).
 */
final class MigrationPreconditionException extends RuntimeException {}
