import { expect, test } from "@playwright/test";

test.describe("Frontend layout", () => {
  test("article page keeps locale-marked spans", async ({ page }) => {
    await page.goto("/sample-article");

    await expect(page.locator("[data-test='site-title']")).toHaveText("Kirby Playground");
    await expect(page).toHaveTitle("Kirby Playground");
    await expect(page.locator("[data-test='page-id']")).toHaveText(
      "sample-article",
    );
    await expect(
      page
        .locator("[data-test='page-text'] span.notranslate[lang][translate='no']")
        .first(),
    ).toBeVisible();
  });
});
