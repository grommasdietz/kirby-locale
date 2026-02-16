import { test, expect } from "@playwright/test";

const PANEL_EMAIL = process.env.KIRBY_USER_EMAIL ?? "admin@kirby-locale.test";
const PANEL_PASSWORD = process.env.KIRBY_USER_PASSWORD ?? "playwright";

async function loginToPanel(page) {
  await page.goto("/panel/login");
  await page.getByLabel("Email").fill(PANEL_EMAIL);
  await page.getByLabel("Password").fill(PANEL_PASSWORD);
  await page.getByRole("button", { name: "Log in" }).click();
  await page.waitForURL(/\/panel/);
}

async function openRenameDialog(page, modelId) {
  await page.goto(`/panel/pages/${modelId}`);
  await page.waitForLoadState("networkidle");
  await page.getByRole("button", { name: "Settings" }).click();
  await page.getByRole("button", { name: "Rename" }).click();
}

test.describe("Panel integration", () => {
  test("admin can log into the Panel", async ({ page }) => {
    await loginToPanel(page);
    await expect(page).toHaveURL(/\/panel/);
    await expect(page.getByRole("heading", { level: 1 }).first()).toBeVisible();
  });

  test("writer locale mark opens a grouped locale picker", async ({ page }) => {
    await loginToPanel(page);
    await page.goto("/panel/pages/home");
    await page.waitForLoadState("networkidle");

    const editor = page.locator(".ProseMirror").first();
    await editor.click();
    await editor.dblclick();

    const localeButton = page.getByRole("button", { name: "Locale" });
    await expect(localeButton).toBeVisible();
    await localeButton.click();

    const localeSelect = page.locator('select[name="locale"]');
    await expect(localeSelect).toBeVisible();
    const optionCount = await page.locator('select[name="locale"] option').count();
    expect(optionCount).toBeGreaterThan(50);

    const groups = await page
      .locator('select[name="locale"] optgroup')
      .evaluateAll((nodes) => nodes.map((node) => node.getAttribute("label")));

    expect(groups).toContain("Site languages");
    expect(groups).toContain("Other languages");
  });

  test("rename dialog injects title locale only for enabled templates", async ({
    page,
  }) => {
    await loginToPanel(page);

    await openRenameDialog(page, "home");
    await expect(page.locator('select[name="title_locale"]')).toBeVisible();

    const groups = await page
      .locator('select[name="title_locale"] optgroup')
      .evaluateAll((nodes) => nodes.map((node) => node.getAttribute("label")));

    expect(groups).toContain("Site languages");
    expect(groups).toContain("Other languages");

    await page.keyboard.press("Escape");
    await openRenameDialog(page, "sandbox");
    await expect(page.locator('select[name="title_locale"]')).toHaveCount(0);
  });
});
