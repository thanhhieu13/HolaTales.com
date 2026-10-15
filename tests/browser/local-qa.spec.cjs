const {test,expect}=require('@playwright/test');
// Explicit credentials and a disposable environment are required. Never seed/reset a live DB here.
test.beforeEach(async({page})=>{
  test.skip(!process.env.E2E_EMAIL || !process.env.E2E_PASSWORD,'Set disposable QA credentials');
  await page.goto('/login');
  await page.locator('input[name=email]').fill(process.env.E2E_EMAIL);
  await page.locator('input[name=password]').fill(process.env.E2E_PASSWORD);
  await page.getByRole('button',{name:/log in|đăng nhập/i}).click();
  await page.waitForURL(/learn|onboarding/);
});
test('plaintext import is retained, processed and readable',async({page})=>{
  await page.goto('/books?import=1');
  await page.getByRole('button',{name:'Plain text',exact:true}).click();
  await page.locator('#plain-text-import-input').fill('Ana visita la biblioteca. Lucía lee un libro.');
  await page.getByRole('button',{name:'Continue',exact:true}).click();
  await page.getByRole('radio',{name:'Create new book'}).check();
  const form=page.locator('#import-dialog');
  await form.locator('input[type=text]').nth(0).fill(`E2E ${Date.now()}`);
  await form.locator('input[type=text]').nth(1).fill('Biblioteca');
  await page.getByRole('button',{name:'Continue',exact:true}).click();
  await form.getByRole('button',{name:'Import',exact:true}).click();
  await expect(form).toContainText(/success/i);
});
test('app geometry and persistent locale',async({page})=>{
  for(const path of ['/learn','/stories','/progress']){
    await page.goto(path);
    await expect(page.locator('body')).toBeVisible();
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  }
});
