---
paths:
  - 'tests/**'
---

# Tests

## Pest functional style is the default test framework
Tests are written in Pest's functional style (it()/test()/expect()). A PHPUnit-style class (extends TestCase with test_ methods) is acceptable but not the house default — prefer Pest for new tests.

## RefreshDatabase: implicit in Feature, explicit in Unit
tests/Feature/** gets RefreshDatabase automatically from tests/Pest.php — don't redeclare it. tests/Unit/** does NOT get it automatically; any Unit test that touches the database must declare uses(TestCase::class, RefreshDatabase::class) (or the trait, in PHPUnit-style Unit tests) explicitly, or it will silently leak data into later tests in the same run.

## Fixtures: factories, except the PPDB setup chain
Test data is built via Model::factory()->create(). The PPDB setup chain (TahunAjaran/JalurPpdb/GelombangPpdb) is the deliberate exception — build it via the shared helpers in tests/Pest.php (firstOrCreate/create), not their factories, because of chained unique constraints. $this->seed() is for shared reference data (Role/Permission), not a fixture rival to factories.

## Mocking is rare; prefer real integration tests
Tests default to full integration (no class doubles). Mockery (mock()/spy()) is reserved for isolating genuinely external collaborators — currently only the Keuangan payment-gateway tests use it. Facade fakes (Http::fake(), Mail::fake(), etc.) are unrelated to this and used freely.

## JSON assertions: array-based, not AssertableJson fluent
Assert JSON responses with assertJson([...]) / assertJsonFragment([...]) arrays. The fluent AssertableJson closure style is not used.
