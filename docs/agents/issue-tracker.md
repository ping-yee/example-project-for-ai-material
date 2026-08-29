# Issue tracker：本機 Markdown

本儲存庫的議題與規格以 Markdown 檔案存放於 `.scratch/`。

## 慣例

- 每項功能使用一個目錄：`.scratch/<feature-slug>/`
- 規格存放於 `.scratch/<feature-slug>/spec.md`
- 實作議題每張 ticket 使用一個獨立檔案，存放於 `.scratch/<feature-slug>/issues/<NN>-<slug>.md`；從 `01` 開始編號，不得合併成單一 tickets 檔案
- Triage 狀態記錄於每個 issue 檔案頂端附近的 `Status:` 行；角色字串詳見 `triage-labels.md`
- 留言與討論歷程附加於檔案底部的 `## Comments` 區段

## 當技能要求「發佈至 issue tracker」

在 `.scratch/<feature-slug>/` 下建立新檔案；目錄不存在時一併建立。

## 當技能要求「取得相關 ticket」

讀取指定路徑的檔案。使用者通常會直接提供檔案路徑或 issue 編號。

## Wayfinding 操作

供 `/wayfinder` 使用。**Map** 是一個主要檔案，每個 **child** ticket 各有一個獨立檔案。

- **Map**：`.scratch/<effort>/map.md`，內文包含 Notes、Decisions-so-far 與 Fog。
- **Child ticket**：`.scratch/<effort>/issues/NN-<slug>.md`，從 `01` 開始編號，並在內文記錄問題。`Type:` 行記錄 ticket 類型（`research`、`prototype`、`grilling` 或 `task`）；`Status:` 行記錄 `claimed` 或 `resolved`。
- **Blocking**：在檔案頂端附近使用 `Blocked by: NN, NN`。當所列檔案的狀態皆為 `resolved` 時，ticket 才解除阻擋。
- **Frontier**：掃描 `.scratch/<effort>/issues/`，找出尚未關閉、未受阻擋且無人領取的檔案；依編號選取第一筆。
- **Claim**：在開始任何工作前，先將 `Status:` 設為 `claimed` 並儲存。
- **Resolve**：在 `## Answer` 區段附加答案，將 `Status:` 設為 `resolved`，再將 context pointer（gist 與連結）附加至 `map.md` 的 Decisions-so-far。
