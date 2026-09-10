---
paths:
  - config/data_navigator.php
---

# Config

## Profilo mediatore: stati via *_at, niente accepted_at
proforma.pratiches ha SOLO questi campi milestone: data_inserimento_pratica, sended_at, approved_at, erogated_at, rejected_at (+ created_at/updated_at/upload_at). NON esiste accepted_at: "deliberata"/"delibera" = approved_at. Il testo di stato_pratica è libero/incoerente (DELIBERATA, PERFEZIONATA, INVIO IN ISTRUTTORIA, ...) → mai filtro primario, solo conferma; per classificarlo usare i flag di pratiches_statos (isworking/isrejected/isestingued). Il background del profilo ha un "Vocabolario degli stati" (§1) che mappa le dizioni utente sui predicati *_at e una "§7 Periodi e Range di Date" che lega ogni metrica al suo campo (acquisizione→data_inserimento_pratica, delibere→approved_at, produzione/OAM→erogated_at, fatturato provvigionale→provvigioni.data_fattura, ENASARCO→venasarcotrimestre.competenza+Trimestre). DataNavigatorProfileTest copre questi punti; se cambi le tables del profilo usa assertContains, non assertSame.
