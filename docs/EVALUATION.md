# Evaluation Methodology

## Research Question

Can a multimodal, Nigerian-context product retrieval system return relevant
marketplace items when shoppers use conversational descriptions instead of
formal catalogue names?

## Offline Retrieval Evaluation

The relevance set is stored at
`backend/database/evaluation/search_queries.json`. Each case contains a natural
shopping query and manually identified relevant products.

The evaluator reports:

- `Precision@K`: relevant products among the first K results.
- `Recall@K`: relevant products retrieved among all known relevant products.
- `MRR`: how early the first relevant product appears.
- `nDCG@K`: ranking quality with greater reward for early relevant results.

Run a reproducible evaluation and create machine-readable evidence:

```bash
./scripts/verify.sh
```

The report is written to `reports/search-evaluation.json`.

### Current Verified Baseline

The reproducible local evaluation generated on June 14, 2026 reports:

| Metric | Score |
| --- | ---: |
| Precision@5 | 0.2571 |
| Recall@5 | 1.0000 |
| MRR | 0.9286 |
| nDCG@5 | 0.9473 |

Precision@5 is expected to be lower when each query has only one or two judged
relevant products. Recall, MRR, and nDCG more directly demonstrate that known
relevant products are consistently retrieved and usually ranked first.

## Functional Evaluation

Laravel feature tests verify:

- Conversational retrieval and vendor-scoped retrieval.
- Embedding indexing and hidden vector fields.
- Search click and zero-result analytics.
- Marketplace storefront exposure.
- Product-detail availability.
- Server-authoritative order pricing.
- Atomic inventory deduction.
- Rejection of orders above available inventory.
- Operational health and commerce KPI endpoints.

## Dataset Evaluation

The large-catalogue validator verifies:

- Exactly 500 vendors.
- Exactly 250,000 products.
- Exactly 500 products per vendor.
- At least 20 distinct categories; the current generator produces 40.
- Valid product image URLs.
- Every product belongs to a known vendor.

## Limitations And Threats To Validity

- The default relevance set is intentionally small and should be expanded with
  multiple human assessors for dissertation-grade statistical claims.
- Synthetic products provide controlled scale but do not perfectly reproduce a
  live marketplace distribution.
- Stable Unsplash images are category relevant but are not unique photographs
  for every synthetic product.
- Local deterministic embeddings prioritize reproducibility over state-of-the-
  art semantic quality.
- Offline relevance metrics do not replace user studies measuring satisfaction,
  task completion, and perceived usefulness.

## Recommended Dissertation Extension

Recruit participants familiar with Nigerian ecommerce, compare keyword search
against KonnectFind, and record task completion time, success rate, result
relevance ratings, and System Usability Scale scores. Report confidence
intervals and inter-rater agreement.
