// Test script to verify theme logic

console.log('=== TESTING THEME LOGIC ===\n');

// Test 1: First visit (no localStorage)
console.log('Test 1: First visit (no localStorage)');
localStorage.clear();
const savedTheme1 = localStorage.getItem('btb_theme');
const userSetTheme1 = localStorage.getItem('btb_theme_user') === '1';
const initialTheme1 = userSetTheme1 && savedTheme1 ? savedTheme1 : 'dark';
console.log(`savedTheme: ${savedTheme1}`);
console.log(`userSetTheme: ${userSetTheme1}`);
console.log(`initialTheme: ${initialTheme1}`);
console.log(`Expected: dark`);
console.log(`Result: ${initialTheme1 === 'dark' ? '✓ PASS' : '✗ FAIL'}\n`);

// Test 2: User switched to light theme
console.log('Test 2: User switched to light theme');
localStorage.setItem('btb_theme', 'light');
localStorage.setItem('btb_theme_user', '1');
const savedTheme2 = localStorage.getItem('btb_theme');
const userSetTheme2 = localStorage.getItem('btb_theme_user') === '1';
const initialTheme2 = userSetTheme2 && savedTheme2 ? savedTheme2 : 'dark';
console.log(`savedTheme: ${savedTheme2}`);
console.log(`userSetTheme: ${userSetTheme2}`);
console.log(`initialTheme: ${initialTheme2}`);
console.log(`Expected: light`);
console.log(`Result: ${initialTheme2 === 'light' ? '✓ PASS' : '✗ FAIL'}\n`);

// Test 3: User switched to dark theme
console.log('Test 3: User switched to dark theme');
localStorage.setItem('btb_theme', 'dark');
localStorage.setItem('btb_theme_user', '1');
const savedTheme3 = localStorage.getItem('btb_theme');
const userSetTheme3 = localStorage.getItem('btb_theme_user') === '1';
const initialTheme3 = userSetTheme3 && savedTheme3 ? savedTheme3 : 'dark';
console.log(`savedTheme: ${savedTheme3}`);
console.log(`userSetTheme: ${userSetTheme3}`);
console.log(`initialTheme: ${initialTheme3}`);
console.log(`Expected: dark`);
console.log(`Result: ${initialTheme3 === 'dark' ? '✓ PASS' : '✗ FAIL'}\n`);

// Test 4: Edge case - empty string
console.log('Test 4: Edge case - empty string (should not happen, but test)');
localStorage.setItem('btb_theme', '');
localStorage.setItem('btb_theme_user', '1');
const savedTheme4 = localStorage.getItem('btb_theme');
const userSetTheme4 = localStorage.getItem('btb_theme_user') === '1';
const initialTheme4 = userSetTheme4 && savedTheme4 ? savedTheme4 : 'dark';
console.log(`savedTheme: "${savedTheme4}" (length: ${savedTheme4 ? savedTheme4.length : 0})`);
console.log(`userSetTheme: ${userSetTheme4}`);
console.log(`initialTheme: ${initialTheme4}`);
console.log(`Expected: dark (empty string is falsy)`);
console.log(`Result: ${initialTheme4 === 'dark' ? '✓ PASS' : '✗ FAIL'}\n`);

// Test 5: Check what happens with different values
console.log('Test 5: Testing different savedTheme values');
const testValues = ['light', 'dark', '', null, undefined, 'invalid'];
testValues.forEach(val => {
  if (val === null || val === undefined) {
    // Can't set null/undefined to localStorage, simulate it
    localStorage.removeItem('btb_theme');
    const saved = localStorage.getItem('btb_theme');
    const userSet = true;
    const initial = userSet && saved ? saved : 'dark';
    console.log(`savedTheme=${val} -> initialTheme=${initial}`);
  } else {
    localStorage.setItem('btb_theme', val);
    const saved = localStorage.getItem('btb_theme');
    const userSet = true;
    const initial = userSet && saved ? saved : 'dark';
    console.log(`savedTheme="${val}" -> initialTheme=${initial}`);
  }
});

console.log('\n=== TESTING toggleTheme LOGIC ===\n');

// Simulate toggleTheme
function simulateToggleTheme(currentTheme) {
  const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
  localStorage.setItem('btb_theme', newTheme);
  localStorage.setItem('btb_theme_user', '1');
  return newTheme;
}

console.log('Test: Starting with dark');
localStorage.setItem('btb_theme', 'dark');
localStorage.setItem('btb_theme_user', '1');
let current = 'dark';
console.log(`Before toggle: ${current}`);
current = simulateToggleTheme(current);
console.log(`After toggle: ${current}`);
console.log(`localStorage btb_theme: ${localStorage.getItem('btb_theme')}`);
console.log(`localStorage btb_theme_user: ${localStorage.getItem('btb_theme_user')}`);
console.log(`Result: ${current === 'light' ? '✓ PASS' : '✗ FAIL'}\n`);

console.log('Test: Toggle again (light -> dark)');
current = simulateToggleTheme(current);
console.log(`After second toggle: ${current}`);
console.log(`localStorage btb_theme: ${localStorage.getItem('btb_theme')}`);
console.log(`localStorage btb_theme_user: ${localStorage.getItem('btb_theme_user')}`);
console.log(`Result: ${current === 'dark' ? '✓ PASS' : '✗ FAIL'}\n`);

console.log('=== ALL TESTS COMPLETE ===');
