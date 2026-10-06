---
paths:
  - 'Modules/Academic/Livewire/Library/**'
---

# Library

## Library screens: domain exceptions, charges, borrower reach
Catch Modules\Core\Domain\Exceptions\DomainException (not PHP's) plus InvalidArgumentException from Actions. Fines, lost-book and stock-take charges go through FIN-02 ad hoc charges and need a fee component chosen at the desk; staff borrowers are never charged. Bulk return re-derives the roster and loans server-side; the client may only flag books as NOT returned. Acquisition approval raises a Stores purchase requisition (source_type=acquisition_request) - never a parallel purchasing path.
