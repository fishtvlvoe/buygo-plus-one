const { test, expect } = require('@playwright/test');

test('test sidebar collapse button', async ({ page }) => {
  // 收集 console 日志
  const consoleLogs = [];
  page.on('console', msg => {
    consoleLogs.push(`[${msg.type()}] ${msg.text()}`);
  });

  // 收集错误
  const errors = [];
  page.on('pageerror', err => {
    errors.push(`ERROR: ${err.message}\n${err.stack}`);
  });

  console.log('1. 导航到商品页面...');
  await page.goto('https://test.buygo.me/buygo-portal/products/', {
    waitUntil: 'networkidle',
    timeout: 30000
  });

  console.log('2. 等待 Vue 加载...');
  await page.waitForTimeout(2000);

  console.log('3. 截图（初始状态）...');
  await page.screenshot({ path: 'sidebar-initial.png', fullPage: true });

  console.log('4. 查找侧边栏和按钮...');
  const sidebar = page.locator('aside').first();
  const sidebarClasses = await sidebar.getAttribute('class');
  console.log(`   初始侧边栏 class: ${sidebarClasses}`);

  // 查找按钮
  const button = page.locator('button').filter({ hasText: '<<' }).or(
    page.locator('aside button').last()
  );

  const buttonCount = await button.count();
  console.log(`   找到 ${buttonCount} 个匹配的按钮`);

  if (buttonCount === 0) {
    console.log('   ❌ 找不到收起按钮！');
    console.log('   所有 button 元素:');
    const buttons = await page.locator('button').all();
    for (let i = 0; i < Math.min(buttons.length, 5); i++) {
      const text = await buttons[i].textContent();
      console.log(`     Button ${i}: "${text}"`);
    }
  } else {
    const isVisible = await button.first().isVisible();
    const isEnabled = await button.first().isEnabled();
    console.log(`   按钮状态 - 可见: ${isVisible}, 启用: ${isEnabled}`);

    console.log('5. 点击按钮...');
    await button.first().click();

    console.log('6. 等待动画完成...');
    await page.waitForTimeout(500);

    console.log('7. 检查侧边栏变化...');
    const sidebarClassesAfter = await sidebar.getAttribute('class');
    console.log(`   点击后侧边栏 class: ${sidebarClassesAfter}`);

    console.log('8. 截图（点击后）...');
    await page.screenshot({ path: 'sidebar-after-click.png', fullPage: true });
  }

  console.log('\n=== Console 日志 ===');
  consoleLogs.forEach(log => console.log(log));

  console.log('\n=== JavaScript 错误 ===');
  if (errors.length === 0) {
    console.log('✅ 没有 JavaScript 错误');
  } else {
    console.log(`❌ 发现 ${errors.length} 个错误:`);
    errors.forEach(err => console.log(err));
  }
});
