---
paths:
  - bootstrap/app.php
---

# Bootstrap

## SerpException is rendered to JSON centrally in bootstrap/app.php
Every Modules\Core\Domain\Exceptions\SerpException subclass carries its own errorCode()/httpStatus()/toErrorEnvelope(), but nothing turned that into an actual HTTP response until CORE-05 added `$exceptions->render(fn (SerpException $e) => response()->json($e->toErrorEnvelope(), $e->httpStatus()))` in bootstrap/app.php's withExceptions(). Don't add per-controller or per-module exception rendering for these — this one registration covers every module's domain exceptions app-wide.
