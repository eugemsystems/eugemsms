---
paths:
  - 'Modules/*/tests/Feature/*.php'
---

# Feature

## Freshly-factoried User has null enum-cast attributes despite DB defaults
UserFactory never sets user_type; the users table defaults it to 'staff' at the DB level, but Eloquent's create() returns the in-memory model with only the attributes it explicitly set — it does not re-fetch, so $user->user_type is null in the same request even though the DB row says 'staff'. Any Action reading $user->user_type->value on a just-created test user will hit "Attempt to read property on null" unless the test passes user_type explicitly (e.g. User::factory()->create(['user_type' => UserType::Staff])).
