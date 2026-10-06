---
paths:
  - 'Modules/Academic/Livewire/Supervision/**'
---

# Supervision

## Supervision screens: reach comes from the signed-in staff record
Use ResolvesSupervisionReach: ownStaff() from auth user, visibleStaffIds() = own, plus departments headed (Department.head_staff_id) with supervision.view, or everyone with supervision.view at School scope. Never accept a teacher/observer staff id from the browser. Approvers cannot approve their own scheme or review their own plan; the observed teacher may only comment (AddObservationTeacherCommentAction), never edit scores. Sensitive observation text must not render for users outside reach.
