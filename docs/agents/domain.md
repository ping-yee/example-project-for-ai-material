# Domain 文件

工程技能在探索程式碼庫時，應依照本文件使用此儲存庫的領域文件。

## 探索前應先閱讀

- 儲存庫根目錄的 **`CONTEXT.md`**；或
- 若根目錄存在 **`CONTEXT-MAP.md`**，它會指向各 context 的 `CONTEXT.md`。請閱讀與目前主題相關的文件。
- **`docs/adr/`**：閱讀與即將處理範圍相關的 ADR。在 multi-context 儲存庫中，也要檢查 `src/<context>/docs/adr/` 內限定於該 context 的決策。

若其中任何檔案不存在，請**直接繼續，不需提示**。不要特別指出檔案缺失，也不要預先建議建立。`/domain-modeling` 技能（可透過 `/grill-with-docs` 與 `/improve-codebase-architecture` 使用）會在術語或決策確定後，視需要建立這些文件。

## 檔案結構

Single-context 儲存庫（適用於大多數儲存庫）：

```text
/
├── CONTEXT.md
├── docs/adr/
│   ├── 0001-event-sourced-orders.md
│   └── 0002-postgres-for-write-model.md
└── src/
```

Multi-context 儲存庫（根目錄存在 `CONTEXT-MAP.md`）：

```text
/
├── CONTEXT-MAP.md
├── docs/adr/                          ← 系統層級的決策
└── src/
    ├── ordering/
    │   ├── CONTEXT.md
    │   └── docs/adr/                  ← context 專屬的決策
    └── billing/
        ├── CONTEXT.md
        └── docs/adr/
```

## 使用 glossary 定義的詞彙

當輸出內容提到領域概念時，例如議題標題、重構提案、假設或測試名稱，請使用 `CONTEXT.md` 中定義的術語。不要改用 glossary 明確避用的同義詞。

若 glossary 尚未收錄所需概念，這代表你可能正在創造專案沒有使用的詞彙，此時應重新考慮；也可能代表領域文件確實有所缺漏，此時應記錄並交由 `/domain-modeling` 處理。

## 指出與 ADR 衝突之處

若輸出內容與現有 ADR 衝突，請明確指出，不要默默覆寫既有決策：

> _與 ADR-0007（event-sourced orders）衝突，但值得重新討論，因為……_
