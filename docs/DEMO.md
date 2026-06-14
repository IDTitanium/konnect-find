# Defense Demonstration Guide

## Preparation

```bash
./scripts/setup.sh --fresh
./scripts/verify.sh
./scripts/run-dev.sh
```

Open `http://localhost:5173`.

## Seven-Minute Demonstration

1. **Problem and contribution:** Explain that shoppers often describe intent
   without knowing a catalogue name, especially using expressions such as
   `owambe` or `pikin`.
2. **Conversational retrieval:** Search for `durable school bag for my pikin`
   and show the relevant product ranked first.
3. **Multimodal design:** Explain that text, image, and combined search share a
   single retrieval pipeline.
4. **Responsive innovation:** Resize to mobile and demonstrate the full-screen
   swipe feed.
5. **Marketplace model:** Show vendor identity, verification, inventory, and
   vendor-scoped search.
6. **Commerce integrity:** Open a product, add it to cart, change quantity, and
   show checkout. Explain server-authoritative pricing and atomic stock updates.
7. **Measurable evidence:** Open Discovery Analytics, then show
   `reports/search-evaluation.json` and the metrics produced by
   `./scripts/verify.sh`.

## Strong Defense Statements

- “The contribution is not simply an ecommerce interface; it is a measurable
  multimodal retrieval system adapted to Nigerian shopping language.”
- “The local mode is deterministic for reproducibility, while the production
  architecture supports pgvector and external embedding models.”
- “The large synthetic catalogue tests scale, while the documented limitations
  distinguish prototype evidence from claims requiring a user study.”
