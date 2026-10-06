---
paths:
  - 'Modules/Academic/Domain/Actions/*Report*.php'
---

# Academic Domain Actions

## Report cards: statuses, gate, versions
term_results.status flow computed->(reviewed)->approved->published, or withheld (fee gate). ComputeTermResultsAction never resets a reviewed/approved/published/withheld status. GenerateReportCardsAction always stores the card, even when withheld; PublishReportCardsAction re-checks the gate and releases a withheld card without regenerating. AmendMarkAction regenerates affected published cards as a NEW version (report_version++). Assessment weights per subject/scope must total 100 (CheckAssessmentWeightsAction) before ComputeTermSubjectResultsAction runs. TemplateRenderer escapes HTML for HtmlDocumentRenderer; never render learner text raw.
