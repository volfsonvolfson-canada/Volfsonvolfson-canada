// Admin Panel JavaScript

// Admin authentication
const ADMIN_CREDENTIALS = {
  username: 'admin',
  password: 'backtobase2024'
};

// Check if user is authenticated
function checkAdminAuth() {
  const isAuthenticated = localStorage.getItem('btb_admin_auth') === 'true';
  if (!isAuthenticated && !window.location.pathname.includes('admin-login')) {
    window.location.href = 'admin-login.html';
  }
}

// Login form handler
function initAdminLogin() {
  const loginForm = document.getElementById('admin-login-form');
  if (!loginForm) return;

  loginForm.addEventListener('submit', (e) => {
    e.preventDefault();
    
    const username = document.getElementById('admin-username').value;
    const password = document.getElementById('admin-password').value;
    const errorDiv = document.getElementById('admin-login-error');
    
    if (username === ADMIN_CREDENTIALS.username && password === ADMIN_CREDENTIALS.password) {
      localStorage.setItem('btb_admin_auth', 'true');
      window.location.href = 'admin.html';
    } else {
      errorDiv.textContent = 'Invalid username or password';
      errorDiv.style.display = 'block';
    }
  });
}

// Logout function
function adminLogout() {
  localStorage.removeItem('btb_admin_auth');
  window.location.href = 'admin-login.html';
}

// Apply styles to Flatpickr navigation arrows
function applyFlatpickrArrowStyles(instance) {
  if (!instance || !instance.calendarContainer) return;
  
  const lightColor = '#e9eef3'; // var(--text)
  const whiteColor = '#ffffff';
  
  // Find all navigation arrows - исключаем стрелки вверх-вниз для года, так как используется выпадающий список
  const arrows = instance.calendarContainer.querySelectorAll(
    '.flatpickr-prev-month, .flatpickr-next-month, .flatpickr-yearDropdown-prev, .flatpickr-yearDropdown-next, .flatpickr-yearDropdown-years button, .flatpickr-yearDropdown-years [class*="prev"], .flatpickr-yearDropdown-years [class*="next"]'
  );
  
  arrows.forEach(arrow => {
    // Apply color to the element itself with !important via style
    arrow.style.setProperty('color', lightColor, 'important');
    arrow.style.setProperty('fill', lightColor, 'important');
    arrow.style.setProperty('stroke', lightColor, 'important');
    
    // Find and style all SVG elements
    const svgs = arrow.querySelectorAll('svg');
    svgs.forEach(svg => {
      svg.style.setProperty('fill', lightColor, 'important');
      svg.style.setProperty('stroke', lightColor, 'important');
      svg.style.setProperty('color', lightColor, 'important');
      svg.setAttribute('fill', lightColor);
      svg.setAttribute('stroke', lightColor);
    });
    
    // Find and style all path elements
    const paths = arrow.querySelectorAll('path');
    paths.forEach(path => {
      path.style.setProperty('fill', lightColor, 'important');
      path.style.setProperty('stroke', lightColor, 'important');
      path.setAttribute('fill', lightColor);
      path.setAttribute('stroke', lightColor);
    });
    
    // Add hover event listener (only if not already added)
    if (!arrow.dataset.arrowStyled) {
      arrow.dataset.arrowStyled = 'true';
      
      arrow.addEventListener('mouseenter', function(e) {
        e.stopPropagation();
        this.style.setProperty('color', whiteColor, 'important');
        this.style.setProperty('fill', whiteColor, 'important');
        this.style.setProperty('stroke', whiteColor, 'important');
        this.querySelectorAll('svg').forEach(svg => {
          svg.style.setProperty('fill', whiteColor, 'important');
          svg.style.setProperty('stroke', whiteColor, 'important');
          svg.style.setProperty('color', whiteColor, 'important');
          svg.setAttribute('fill', whiteColor);
          svg.setAttribute('stroke', whiteColor);
        });
        this.querySelectorAll('path').forEach(path => {
          path.style.setProperty('fill', whiteColor, 'important');
          path.style.setProperty('stroke', whiteColor, 'important');
          path.setAttribute('fill', whiteColor);
          path.setAttribute('stroke', whiteColor);
        });
      });
      
      arrow.addEventListener('mouseleave', function(e) {
        e.stopPropagation();
        this.style.setProperty('color', lightColor, 'important');
        this.style.setProperty('fill', lightColor, 'important');
        this.style.setProperty('stroke', lightColor, 'important');
        this.querySelectorAll('svg').forEach(svg => {
          svg.style.setProperty('fill', lightColor, 'important');
          svg.style.setProperty('stroke', lightColor, 'important');
          svg.style.setProperty('color', lightColor, 'important');
          svg.setAttribute('fill', lightColor);
          svg.setAttribute('stroke', lightColor);
        });
        this.querySelectorAll('path').forEach(path => {
          path.style.setProperty('fill', lightColor, 'important');
          path.style.setProperty('stroke', lightColor, 'important');
          path.setAttribute('fill', lightColor);
          path.setAttribute('stroke', lightColor);
        });
      });
    }
  });
}

// Two-level navigation system
let currentPrimary = 'bookings'; // Default primary section

// Switch primary section (Bookings Management, Content Management, Account Management)
function switchPrimarySection(primaryName) {
  currentPrimary = primaryName;
  
  // Remove active class from all primary tabs
  document.querySelectorAll('.admin-nav-tab-primary').forEach(tab => {
    tab.classList.remove('active');
  });
  
  // Add active class to clicked primary tab
  const activePrimaryTab = document.querySelector(`[data-primary="${primaryName}"]`);
  if (activePrimaryTab) {
    activePrimaryTab.classList.add('active');
  }
  
  // Hide all secondary tab groups
  document.querySelectorAll('.admin-nav-tabs-secondary').forEach(group => {
    group.classList.add('hidden');
  });
  
  // Show secondary tabs for selected primary section
  const secondaryTabs = document.querySelector(`.admin-nav-tabs-secondary[data-primary="${primaryName}"]`);
  if (secondaryTabs) {
    secondaryTabs.classList.remove('hidden');
  }
  
  // Activate first secondary tab (Dashboard) for the selected primary section
  if (secondaryTabs) {
    const firstSecondaryTab = secondaryTabs.querySelector('.admin-nav-tab-secondary');
    if (firstSecondaryTab) {
      const sectionName = firstSecondaryTab.getAttribute('data-section');
      showSection(sectionName);
    }
  }
}

// Navigation between sections (secondary level)
function showSection(sectionName) {
  // Check for unsaved changes in retreat section before switching
  // Only check if variables and functions are defined (retreat section was loaded)
  if (typeof retreatHasUnsavedChanges !== 'undefined' && 
      typeof retreatIsSaving !== 'undefined' && 
      typeof retreatAutoSaveTimer !== 'undefined' &&
      typeof autoSaveRetreatContent === 'function' &&
      retreatHasUnsavedChanges && 
      !retreatIsSaving && 
      sectionName !== 'retreat-workshop') {
    // Force save before switching
    if (retreatAutoSaveTimer) {
      clearTimeout(retreatAutoSaveTimer);
      retreatAutoSaveTimer = null;
    }
    try {
      autoSaveRetreatContent().then(() => {
        // Continue switching after save completes
        performSectionSwitch(sectionName);
      }).catch(() => {
        // Even if save fails, allow switching (user can come back)
        performSectionSwitch(sectionName);
      });
    } catch (error) {
      console.error('Error in auto-save before section switch:', error);
      // If auto-save fails, still allow switching
      performSectionSwitch(sectionName);
    }
  } else {
    performSectionSwitch(sectionName);
  }
}

function performSectionSwitch(sectionName) {
  // Reset retreat auto-save state when switching away
  // Only reset if variables and functions are defined (retreat section was loaded)
  if (typeof retreatHasUnsavedChanges !== 'undefined' && 
      typeof updateRetreatSaveStatus === 'function' &&
      typeof retreatAutoSaveTimer !== 'undefined' &&
      sectionName !== 'retreat-workshop') {
    try {
      retreatHasUnsavedChanges = false;
      updateRetreatSaveStatus('', '');
      if (retreatAutoSaveTimer) {
        clearTimeout(retreatAutoSaveTimer);
        retreatAutoSaveTimer = null;
      }
    } catch (error) {
      console.error('Error resetting retreat auto-save state:', error);
      // Continue with section switch even if reset fails
    }
  }
  
  // Hide all sections
  document.querySelectorAll('.admin-section').forEach(section => {
    section.style.display = 'none';
  });
  
  // Remove active class from all secondary nav tabs
  document.querySelectorAll('.admin-nav-tab-secondary').forEach(tab => {
    tab.classList.remove('active');
  });
  
  // Show selected section
  const targetSection = document.getElementById(`${sectionName}-section`);
  if (targetSection) {
    targetSection.style.display = 'block';
  }
  
  // Add active class to clicked secondary tab
  const activeTab = document.querySelector(`[data-section="${sectionName}"]`);
  if (activeTab) {
    activeTab.classList.add('active');
  }
  
  // Load section data
  loadSectionData(sectionName);
}

// Load data for specific section
function loadSectionData(sectionName) {
  switch(sectionName) {
    case 'homepage':
      loadHomepageData();
      initHomepageImageUpload();
      break;
    case 'floorplan':
      loadFloorplanData();
      initFloorplanImageUpload();
      break;
    case 'room-basement':
      loadRoomBasementData();
      initRoomBasementImageUpload();
      break;
    case 'room-ground-queen':
      loadRoomGroundQueenData();
      initRoomGroundQueenImageUpload();
      break;
    case 'room-ground-twin':
      loadRoomGroundTwinData();
      initRoomGroundTwinImageUpload();
      break;
    case 'room-second':
      loadRoomSecondData();
      initRoomSecondImageUpload();
      break;
    case 'massage':
      loadMassageData();
      loadMassageImagesData();
      initMassageImageUpload();
      initMassageSaveHandler();
      break;
    case 'retreat-workshop':
      loadRetreatWorkshopData();
      loadRetreatImagesData();
      initRetreatImageUpload();
      initRetreatSaveHandler();
      initRetreatHelperUI();
      // Initialize auto-save after a short delay to ensure all elements are loaded
      setTimeout(() => {
        if (typeof initRetreatAutoSave === 'function') {
          initRetreatAutoSave();
        }
      }, 100);
      break;
    case 'special':
      loadSpecialData();
      loadSpecialImagesData();
      initSpecialImageUpload();
      initSpecialSaveHandler();
      break;
    case 'about':
      loadAboutData();
      loadAboutImagesData();
      initAboutImageUpload();
      initAboutSaveHandler();
      break;
    case 'contact':
      loadContactData();
      break;
    case 'wellness-experiences':
      loadWellnessExperiencesData();
      initWellnessExperiencesImageUpload();
      break;
    case 'rooms':
      loadRoomsData();
      break;
    case 'yoga':
      loadYogaData();
      break;
    case 'images':
      loadImagesData();
      break;
    case 'bookings':
      loadBookingsData();
      initBookingsFilters();
      break;
    case 'calendar':
      loadCalendarData();
      loadBlockedDates();
      initCalendarBlocking();
      break;
    case 'accounts':
      loadAccountsData();
      initAccountsFilters();
      break;
    case 'bookings-dashboard':
      initDashboardFilters('bookings');
      updateBookingsDashboardStats();
      break;
    case 'accounts-dashboard':
      initDashboardFilters('accounts');
      updateAccountsDashboardStats();
      break;
    case 'dashboard':
      // Legacy support
      updateDashboardStats();
      break;
  }
}

// Homepage management
async function loadHomepageData() {
  // Load existing homepage content from Content settings
  try {
    console.log('Loading homepage data...');
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    console.log('Response status:', response.status);
    
    if (response.ok) {
      const result = await response.json();
      console.log('API response:', result);
      
      if (result.success && result.data) {
        console.log('Populating fields with data:', result.data);
        // Populate fields with existing content
        document.getElementById('homepage-main-description').value = result.data.homepageDescription || '';
        document.getElementById('homepage-main-subtitle').value = result.data.homepageSubtitle || '';
        
        // Load hero images with preview
        const heroImageUrl = result.data.heroImageUrl || '';
        const hero2ImageUrl = result.data.hero2ImageUrl || '';
        
        console.log('Hero image URL from API:', heroImageUrl);
        console.log('Hero2 image URL from API:', hero2ImageUrl);
        
        // Update hero image preview and path
        const heroPreview = document.getElementById('hero-image-preview');
        const heroPath = document.getElementById('hero-image-path');
        console.log('Hero preview element:', heroPreview);
        console.log('Hero path element:', heroPath);
        
        if (heroImageUrl) {
          if (heroPreview && heroPath) {
            const img = document.createElement('img');
            img.src = heroImageUrl + '?v=' + Date.now();
            heroPreview.innerHTML = '';
            heroPreview.appendChild(img);
            heroPreview.style.display = 'block';
            heroPath.textContent = heroImageUrl;
            heroPath.style.display = 'block';
            console.log('Hero image preview updated:', heroImageUrl);
          } else {
            console.error('Hero preview or path elements not found');
          }
        } else {
          console.log('No hero image URL in API response');
        }
        
        // Update hero2 image preview and path
        const hero2Preview = document.getElementById('hero2-image-preview');
        const hero2Path = document.getElementById('hero2-image-path');
        console.log('Hero2 preview element:', hero2Preview);
        console.log('Hero2 path element:', hero2Path);
        
        if (hero2ImageUrl) {
          if (hero2Preview && hero2Path) {
            const img = document.createElement('img');
            img.src = hero2ImageUrl + '?v=' + Date.now();
            hero2Preview.innerHTML = '';
            hero2Preview.appendChild(img);
            hero2Preview.style.display = 'block';
            hero2Path.textContent = hero2ImageUrl;
            hero2Path.style.display = 'block';
            console.log('Hero2 image preview updated:', hero2ImageUrl);
          } else {
            console.error('Hero2 preview or path elements not found');
          }
        } else {
          console.log('No hero2 image URL in API response');
        }
        
        // Save loaded data to localStorage (like Floor Plan does)
        // Preserve existing data and update with loaded values
        let existingData = {};
        const existingStored = localStorage.getItem('btb_content');
        if (existingStored) {
          try {
            existingData = JSON.parse(existingStored);
          } catch (e) {
            console.error('Failed to parse existing localStorage data:', e);
          }
        }
        
        const contentData = {
          ...existingData, // Preserve existing data
          homepageDescription: result.data.homepageDescription || existingData.homepageDescription || '',
          homepageSubtitle: result.data.homepageSubtitle || existingData.homepageSubtitle || '',
          heroImageUrl: heroImageUrl || existingData.heroImageUrl || '',
          hero2ImageUrl: hero2ImageUrl || existingData.hero2ImageUrl || '',
          // Wellness images
          wellnessMassageImageUrl: result.data.wellnessMassageImageUrl || existingData.wellnessMassageImageUrl || '',
          wellnessYogaImageUrl: result.data.wellnessYogaImageUrl || existingData.wellnessYogaImageUrl || '',
          wellnessSaunaImageUrl: result.data.wellnessSaunaImageUrl || existingData.wellnessSaunaImageUrl || '',
          // Room cards images
          roomBasementCardImageUrl: result.data.roomBasementCardImageUrl || existingData.roomBasementCardImageUrl || '',
          roomGroundQueenCardImageUrl: result.data.roomGroundQueenCardImageUrl || existingData.roomGroundQueenCardImageUrl || '',
          roomGroundTwinCardImageUrl: result.data.roomGroundTwinCardImageUrl || existingData.roomGroundTwinCardImageUrl || '',
          roomSecondCardImageUrl: result.data.roomSecondCardImageUrl || existingData.roomSecondCardImageUrl || '',
          // Massage page images
          massageRelaxingImageUrl: result.data.massageRelaxingImageUrl || existingData.massageRelaxingImageUrl || '',
          massageDeepTissueImageUrl: result.data.massageDeepTissueImageUrl || existingData.massageDeepTissueImageUrl || '',
          massageReikiImageUrl: result.data.massageReikiImageUrl || existingData.massageReikiImageUrl || '',
          massageSaunaImageUrl: result.data.massageSaunaImageUrl || existingData.massageSaunaImageUrl || '',
          massageRoomBookingImageUrl: result.data.massageRoomBookingImageUrl || existingData.massageRoomBookingImageUrl || '',
          // Retreat and Workshop page images
          retreatHeroImageUrl: result.data.retreatHeroImageUrl || existingData.retreatHeroImageUrl || '',
          retreatForestImageUrl: result.data.retreatForestImageUrl || existingData.retreatForestImageUrl || '',
          retreatIndoorImageUrl: result.data.retreatIndoorImageUrl || existingData.retreatIndoorImageUrl || '',
          retreatTheatreImageUrl: result.data.retreatTheatreImageUrl || existingData.retreatTheatreImageUrl || '',
          // Special page images
          specialHeroImageUrl: result.data.specialHeroImageUrl || existingData.specialHeroImageUrl || '',
          specialPoolsImageUrl: result.data.specialPoolsImageUrl || existingData.specialPoolsImageUrl || '',
          specialDiningImageUrl: result.data.specialDiningImageUrl || existingData.specialDiningImageUrl || '',
          // About us page images
          aboutHeroImageUrl: result.data.aboutHeroImageUrl || existingData.aboutHeroImageUrl || '',
          aboutFounderImageUrl: result.data.aboutFounderImageUrl || existingData.aboutFounderImageUrl || '',
          aboutProcterImageUrl: result.data.aboutProcterImageUrl || existingData.aboutProcterImageUrl || ''
        };
        localStorage.setItem('btb_content', JSON.stringify(contentData));
        console.log('Homepage data saved to localStorage:', contentData);
      }
    }
  } catch (error) {
    console.log('Failed to load homepage data');
  }
  
  // Also load Floor Plan and Rooms data
  await loadFloorplanData();
  await loadHomepageRoomsData();
}

// Floor Plan management
async function loadFloorplanData() {
  // Load existing floorplan content
  try {
    console.log('Loading floorplan data...');
    const formData = new FormData();
    formData.append('action', 'get_floorplan');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    console.log('Response status:', response.status);
    
    if (response.ok) {
      const result = await response.json();
      console.log('API response:', result);
      
      if (result.success && result.data) {
        console.log('Populating fields with data:', result.data);
        // Populate fields with existing content
        // API returns fields with underscores, but we need to map them to the correct field names
        console.log('Setting basement-subtitle to:', result.data.basement_subtitle);
        document.getElementById('basement-subtitle').value = result.data.basement_subtitle || '';
        console.log('Setting basement-description to:', result.data.basement_description);
        document.getElementById('basement-description').value = result.data.basement_description || '';
        // API returns camelCase, but we need to check both formats for compatibility
        const basementImage = result.data.basementImageUrl || result.data.basement_image_url || '';
        console.log('Setting basement-image-url to:', basementImage);
        if (basementImage) {
          // Show preview of existing image
          const preview = document.getElementById('basement-image-preview');
          const pathDisplay = document.getElementById('basement-image-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = basementImage + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = basementImage;
            pathDisplay.style.display = 'block';
          }
        }
        console.log('Setting ground-subtitle to:', result.data.ground_subtitle);
        document.getElementById('ground-subtitle').value = result.data.ground_subtitle || '';
        console.log('Setting ground-description to:', result.data.ground_description);
        document.getElementById('ground-description').value = result.data.ground_description || '';
        // Universal: use ground_image_url (fallback to ground_queen_image for compatibility)
        // API returns camelCase (groundQueenImage), but check both formats
        const groundImage = result.data.groundQueenImage || result.data.ground_image_url || result.data.ground_queen_image || '';
        console.log('Setting ground-image-url to:', groundImage);
        if (groundImage) {
          // Show preview of existing image
          const preview = document.getElementById('ground-image-preview');
          const pathDisplay = document.getElementById('ground-image-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = groundImage + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = groundImage;
            pathDisplay.style.display = 'block';
          }
        }
        // ground-twin-image field removed
        console.log('Setting loft-subtitle to:', result.data.loft_subtitle);
        document.getElementById('loft-subtitle').value = result.data.loft_subtitle || '';
        console.log('Setting loft-description to:', result.data.loft_description);
        document.getElementById('loft-description').value = result.data.loft_description || '';
        // API returns camelCase (loftImageUrl), but check both formats for compatibility
        const loftImage = result.data.loftImageUrl || result.data.loft_image_url || '';
        console.log('Setting loft-image-url to:', loftImage);
        if (loftImage) {
          // Show preview of existing image
          const preview = document.getElementById('loft-image-preview');
          const pathDisplay = document.getElementById('loft-image-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = loftImage + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = loftImage;
            pathDisplay.style.display = 'block';
          }
        }
        console.log('Fields populated successfully');
      } else {
        console.log('No data received or API failed');
      }
    } else {
      console.log('Response not OK:', response.status);
    }
  } catch (error) {
    console.log('Failed to load floorplan data:', error);
  }
}

// Rooms management
function loadRoomsData() {
  const roomsList = document.getElementById('rooms-list');
  if (!roomsList) return;
  
  const rooms = getStoredData('btb_rooms') || getDefaultRooms();
  
  roomsList.innerHTML = rooms.map((room, index) => `
    <div class="admin-room-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
      <div style="display: flex; justify-content: space-between; align-items: start;">
        <div style="flex: 1;">
          <h4 style="margin: 0 0 8px 0; color: #2d3748;">${room.name}</h4>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Price: ${room.price} CAD/night</p>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Capacity: ${room.capacity} guests</p>
          <p style="margin: 0; color: #718096; font-size: 14px;">${room.description}</p>
        </div>
        <div style="display: flex; gap: 8px; margin-left: 16px;">
          <button class="admin-btn admin-btn-secondary" onclick="editRoom(${index})">Edit</button>
          <button class="admin-btn admin-btn-danger" onclick="deleteRoom(${index})">Delete</button>
        </div>
      </div>
    </div>
  `).join('');
}

function getDefaultRooms() {
  return [
    {
      name: 'Basement — Queen bed',
      price: 140,
      capacity: 2,
      type: 'queen',
      description: 'Cozy room next to the home cinema and sauna. Perfect for two.'
    },
    {
      name: 'Ground floor — Queen bed',
      price: 130,
      capacity: 2,
      type: 'queen',
      description: 'Compact, bright room with access to the fireplace lounge.'
    },
    {
      name: 'Ground floor — Twin beds',
      price: 125,
      capacity: 2,
      type: 'twin',
      description: 'Great for friends or colleagues. Close to the kitchen and massage hall.'
    },
    {
      name: 'Second floor (entire) — Queen bed',
      price: 210,
      capacity: 2,
      type: 'suite',
      description: 'Separate kitchen and shower, study, and a balcony with lake view.'
    }
  ];
}

// Massage management
function loadMassageData() {
  const massageList = document.getElementById('massage-list');
  if (!massageList) return;
  
  const massages = getStoredData('btb_massage_services') || getDefaultMassageServices();
  
  massageList.innerHTML = massages.map((service, index) => `
    <div class="admin-service-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
      <div style="display: flex; justify-content: space-between; align-items: start;">
        <div style="flex: 1;">
          <h4 style="margin: 0 0 8px 0; color: #2d3748;">${service.name}</h4>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Duration: ${service.duration} minutes</p>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Price: ${service.price} CAD</p>
          <p style="margin: 0; color: #718096; font-size: 14px;">${service.description}</p>
        </div>
        <div style="display: flex; gap: 8px; margin-left: 16px;">
          <button class="admin-btn admin-btn-secondary" onclick="editMassage(${index})">Edit</button>
          <button class="admin-btn admin-btn-danger" onclick="deleteMassage(${index})">Delete</button>
        </div>
      </div>
    </div>
  `).join('');
}

// Load massage page images
async function loadMassageImagesData() {
  console.log('Loading massage images data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        // Load images with preview
        const relaxingImageUrl = result.data.massageRelaxingImageUrl || '';
        const deepTissueImageUrl = result.data.massageDeepTissueImageUrl || '';
        const reikiImageUrl = result.data.massageReikiImageUrl || '';
        const saunaImageUrl = result.data.massageSaunaImageUrl || '';
        const roomBookingImageUrl = result.data.massageRoomBookingImageUrl || '';
        
        // Update relaxing image preview
        if (relaxingImageUrl) {
          const preview = document.getElementById('massage-relaxing-preview');
          const pathDisplay = document.getElementById('massage-relaxing-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = relaxingImageUrl + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = relaxingImageUrl;
            pathDisplay.style.display = 'block';
          }
        }
        
        // Update deep tissue image preview
        if (deepTissueImageUrl) {
          const preview = document.getElementById('massage-deep-tissue-preview');
          const pathDisplay = document.getElementById('massage-deep-tissue-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = deepTissueImageUrl + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = deepTissueImageUrl;
            pathDisplay.style.display = 'block';
          }
        }
        
        // Update reiki image preview
        if (reikiImageUrl) {
          const preview = document.getElementById('massage-reiki-preview');
          const pathDisplay = document.getElementById('massage-reiki-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = reikiImageUrl + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = reikiImageUrl;
            pathDisplay.style.display = 'block';
          }
        }
        
        // Update sauna image preview
        if (saunaImageUrl) {
          const preview = document.getElementById('massage-sauna-preview');
          const pathDisplay = document.getElementById('massage-sauna-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = saunaImageUrl + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = saunaImageUrl;
            pathDisplay.style.display = 'block';
          }
        }
        
        // Update room booking image preview
        if (roomBookingImageUrl) {
          const preview = document.getElementById('massage-room-booking-preview');
          const pathDisplay = document.getElementById('massage-room-booking-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = roomBookingImageUrl + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = roomBookingImageUrl;
            pathDisplay.style.display = 'block';
          }
        }
        
        // Save to localStorage
        const stored = localStorage.getItem('btb_massage_images') || '{}';
        const storedJson = JSON.parse(stored);
        const massageImagesData = {
          ...storedJson,
          relaxing: relaxingImageUrl || storedJson.relaxing || '',
          deepTissue: deepTissueImageUrl || storedJson.deepTissue || '',
          reiki: reikiImageUrl || storedJson.reiki || '',
          sauna: saunaImageUrl || storedJson.sauna || '',
          roomBooking: roomBookingImageUrl || storedJson.roomBooking || ''
        };
        localStorage.setItem('btb_massage_images', JSON.stringify(massageImagesData));
        console.log('Massage images data saved to localStorage');
      }
    }
  } catch (error) {
    console.log('Failed to load massage images data:', error);
  }
}

// Initialize massage image upload
function initMassageImageUpload() {
  const uploadConfigs = [
    {
      buttonId: 'massage-relaxing-upload-btn',
      inputId: 'massage-relaxing-upload',
      previewId: 'massage-relaxing-preview',
      pathId: 'massage-relaxing-path',
      imageType: 'massage-relaxing'
    },
    {
      buttonId: 'massage-deep-tissue-upload-btn',
      inputId: 'massage-deep-tissue-upload',
      previewId: 'massage-deep-tissue-preview',
      pathId: 'massage-deep-tissue-path',
      imageType: 'massage-deep-tissue'
    },
    {
      buttonId: 'massage-reiki-upload-btn',
      inputId: 'massage-reiki-upload',
      previewId: 'massage-reiki-preview',
      pathId: 'massage-reiki-path',
      imageType: 'massage-reiki'
    },
    {
      buttonId: 'massage-sauna-upload-btn',
      inputId: 'massage-sauna-upload',
      previewId: 'massage-sauna-preview',
      pathId: 'massage-sauna-path',
      imageType: 'massage-sauna'
    },
    {
      buttonId: 'massage-room-booking-upload-btn',
      inputId: 'massage-room-booking-upload',
      previewId: 'massage-room-booking-preview',
      pathId: 'massage-room-booking-path',
      imageType: 'massage-room-booking'
    }
  ];

  uploadConfigs.forEach(config => {
    const uploadBtn = document.getElementById(config.buttonId);
    const fileInput = document.getElementById(config.inputId);
    const preview = document.getElementById(config.previewId);
    const pathDisplay = document.getElementById(config.pathId);

    if (uploadBtn && fileInput) {
      uploadBtn.addEventListener('click', () => {
        fileInput.click();
      });

      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          await uploadImage(file, config.imageType, preview, pathDisplay, {
            localStorageKey: 'btb_massage_images',
            fieldNameMapper: (type) => {
              const typeMap = {
                'massage-relaxing': 'relaxing',
                'massage-deep-tissue': 'deepTissue',
                'massage-reiki': 'reiki',
                'massage-sauna': 'sauna',
                'massage-room-booking': 'roomBooking'
              };
              return typeMap[type] || type;
            },
            reloadFunction: loadMassageImagesData,
            imageNameMapper: (type) => {
              const nameMap = {
                'massage-relaxing': 'Relaxing Massage',
                'massage-deep-tissue': 'Deep Tissue Massage',
                'massage-reiki': 'Reiki Energy Healing',
                'massage-sauna': 'Sauna',
                'massage-room-booking': 'Room Booking'
              };
              return nameMap[type] || type;
            }
          });
        }
      });
    }
  });
}

// Initialize save handler for massage images
function initMassageSaveHandler() {
  const saveBtn = document.getElementById('save-massage-images');
  if (saveBtn) {
    saveBtn.addEventListener('click', async () => {
      const relaxingPath = document.getElementById('massage-relaxing-path')?.textContent || '';
      const deepTissuePath = document.getElementById('massage-deep-tissue-path')?.textContent || '';
      const reikiPath = document.getElementById('massage-reiki-path')?.textContent || '';
      const saunaPath = document.getElementById('massage-sauna-path')?.textContent || '';
      const roomBookingPath = document.getElementById('massage-room-booking-path')?.textContent || '';
      
      // Get existing content
      const stored = localStorage.getItem('btb_content') || '{}';
      let contentData = {};
      try {
        contentData = JSON.parse(stored);
      } catch (e) {
        console.error('Failed to parse btb_content:', e);
      }
      
      // Update with massage images
      contentData.massageRelaxingImageUrl = relaxingPath;
      contentData.massageDeepTissueImageUrl = deepTissuePath;
      contentData.massageReikiImageUrl = reikiPath;
      contentData.massageSaunaImageUrl = saunaPath;
      contentData.massageRoomBookingImageUrl = roomBookingPath;
      
      // Save to localStorage
      localStorage.setItem('btb_content', JSON.stringify(contentData));
      
      // Try to save to server
      try {
        const formData = new FormData();
        formData.append('action', 'save_content');
        formData.append('massage_relaxing_image_url', relaxingPath);
        formData.append('massage_deep_tissue_image_url', deepTissuePath);
        formData.append('massage_reiki_image_url', reikiPath);
        formData.append('massage_sauna_image_url', saunaPath);
        formData.append('massage_room_booking_image_url', roomBookingPath);
        
        const response = await fetch('api.php', {
          method: 'POST',
          body: formData
        });
        
        if (response.ok) {
          showStatus('Massage images saved successfully!');
        } else {
          showStatus('Massage images saved to localStorage. Server save may have failed.', 'warning');
        }
      } catch (error) {
        console.error('Error saving massage images:', error);
        showStatus('Massage images saved to localStorage. Server save failed.', 'warning');
      }
    });
  }
}

function getDefaultMassageServices() {
  return [
    {
      name: 'Relaxing Massage',
      duration: 60,
      price: 110,
      type: 'relaxing',
      description: 'This gentle massage, perfect for those who want to unwind and restore their energy.'
    },
    {
      name: 'Relaxing Massage',
      duration: 90,
      price: 160,
      type: 'relaxing',
      description: 'Extended relaxing massage session for deeper relaxation.'
    },
    {
      name: 'Deep Tissue Massage',
      duration: 60,
      price: 120,
      type: 'deep-tissue',
      description: 'For targeted relief of muscle tension and pain.'
    },
    {
      name: 'Deep Tissue Massage',
      duration: 90,
      price: 170,
      type: 'deep-tissue',
      description: 'Extended deep tissue massage for chronic issues.'
    },
    {
      name: 'Reiki Energy Healing',
      duration: 15,
      price: 25,
      type: 'reiki',
      description: 'Gentle energy healing technique that promotes relaxation.'
    },
    {
      name: 'Reiki Energy Healing',
      duration: 30,
      price: 50,
      type: 'reiki',
      description: 'Extended Reiki session for deeper energy work.'
    }
  ];
}

// Yoga management
function loadYogaData() {
  const yogaList = document.getElementById('yoga-list');
  if (!yogaList) return;
  
  const yogaServices = getStoredData('btb_yoga_services') || getDefaultYogaServices();
  
  yogaList.innerHTML = yogaServices.map((service, index) => `
    <div class="admin-service-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
      <div style="display: flex; justify-content: space-between; align-items: start;">
        <div style="flex: 1;">
          <h4 style="margin: 0 0 8px 0; color: #2d3748;">${service.name}</h4>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Duration: ${service.duration} minutes</p>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Price: ${service.price} CAD</p>
          <p style="margin: 0; color: #718096; font-size: 14px;">${service.description}</p>
        </div>
        <div style="display: flex; gap: 8px; margin-left: 16px;">
          <button class="admin-btn admin-btn-secondary" onclick="editYoga(${index})">Edit</button>
          <button class="admin-btn admin-btn-danger" onclick="deleteYoga(${index})">Delete</button>
        </div>
      </div>
    </div>
  `).join('');
}

function getDefaultYogaServices() {
  return [
    {
      name: 'Individual Session',
      duration: 60,
      price: 90,
      type: 'individual',
      description: 'One-on-one yoga session in the forest with an experienced instructor.'
    },
    {
      name: 'Couple Session',
      duration: 60,
      price: 120,
      type: 'couple',
      description: 'Yoga session for couples in a peaceful forest setting.'
    },
    {
      name: 'Group Training',
      duration: 60,
      price: 0,
      type: 'group',
      description: 'Group yoga sessions - price and time arranged individually.'
    }
  ];
}

// Content management
async function loadContentData() {
  // Try to load from API first
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const content = result.data;
        
        document.getElementById('homepage-description').value = content.homepageDescription || '';
        document.getElementById('homepage-subtitle').value = content.homepageSubtitle || '';
        document.getElementById('contact-phone').value = content.contactPhone || '+1 (555) 123‑4567';
        document.getElementById('contact-email').value = content.contactEmail || 'hello@backtobase.example';
        document.getElementById('contact-address').value = content.contactAddress || 'British Columbia, Canada';
        return;
      }
    }
  } catch (error) {
    console.log('Failed to load from API, using localStorage');
  }
  
  // Fallback to localStorage
  const content = getStoredData('btb_content') || getDefaultContent();
  
  document.getElementById('homepage-description').value = content.homepageDescription || '';
  document.getElementById('homepage-subtitle').value = content.homepageSubtitle || '';
  document.getElementById('contact-phone').value = content.contactPhone || '+1 (555) 123‑4567';
  document.getElementById('contact-email').value = content.contactEmail || 'hello@backtobase.example';
  document.getElementById('contact-address').value = content.contactAddress || 'British Columbia, Canada';
}

function getDefaultContent() {
  return {
    homepageDescription: 'Back to Base is a countryside guesthouse in Nelson, British Columbia, where you can rent a room or book the entire house for a vacation, retreat, or wellness getaway. Guests can restore their energy with a relaxing massage and enjoy comfortable accommodation surrounded by mountains and forest.',
    homepageSubtitle: 'Our cozy rooms and inspiring atmosphere make this the perfect place for solitude, meditation, yoga retreats, or simply a peaceful holiday in nature.',
    contactPhone: '+1 (555) 123‑4567',
    contactEmail: 'hello@backtobase.example',
    contactAddress: 'British Columbia, Canada'
  };
}

// Images management
function loadImagesData() {
  const imagesContainer = document.getElementById('current-images');
  if (!imagesContainer) return;
  
  const images = getStoredData('btb_images') || [];
  
  imagesContainer.innerHTML = images.map((image, index) => `
    <div class="admin-image-item" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center;">
      <img src="${image.url}" alt="${image.name}" style="max-width: 150px; max-height: 150px; border-radius: 4px; margin-bottom: 8px;">
      <p style="margin: 0 0 8px 0; font-size: 14px; color: #2d3748;">${image.name}</p>
      <button class="admin-btn admin-btn-danger" onclick="deleteImage(${index})">Delete</button>
    </div>
  `).join('');
}

// Utility functions
function getStoredData(key) {
  try {
    const data = localStorage.getItem(key);
    return data ? JSON.parse(data) : null;
  } catch (e) {
    return null;
  }
}

function setStoredData(key, data) {
  try {
    localStorage.setItem(key, JSON.stringify(data));
    return true;
  } catch (e) {
    return false;
  }
}

function showStatus(message, type = 'success') {
  const statusDiv = document.createElement('div');
  statusDiv.className = `admin-status ${type}`;
  statusDiv.textContent = message;
  
  const container = document.querySelector('.admin-container');
  container.insertBefore(statusDiv, container.firstChild);
  
  setTimeout(() => {
    statusDiv.remove();
  }, 3000);
}

// Form handlers
function initAdminForms() {
  // Add room form
  const addRoomForm = document.getElementById('add-room-form');
  if (addRoomForm) {
    addRoomForm.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const room = {
        name: document.getElementById('room-name').value,
        price: parseInt(document.getElementById('room-price').value),
        capacity: parseInt(document.getElementById('room-capacity').value),
        type: document.getElementById('room-type').value,
        description: document.getElementById('room-description').value
      };
      
      const rooms = getStoredData('btb_rooms') || getDefaultRooms();
      rooms.push(room);
      setStoredData('btb_rooms', rooms);
      
      showStatus('Room added successfully!');
      loadRoomsData();
      addRoomForm.reset();
    });
  }
  
  // Add massage form
  const addMassageForm = document.getElementById('add-massage-form');
  if (addMassageForm) {
    addMassageForm.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const service = {
        name: document.getElementById('massage-name').value,
        duration: parseInt(document.getElementById('massage-duration').value),
        price: parseInt(document.getElementById('massage-price').value),
        type: document.getElementById('massage-type').value,
        description: document.getElementById('massage-description').value
      };
      
      const services = getStoredData('btb_massage_services') || getDefaultMassageServices();
      services.push(service);
      setStoredData('btb_massage_services', services);
      
      showStatus('Massage service added successfully!');
      loadMassageData();
      addMassageForm.reset();
    });
  }
  
  // Add yoga form
  const addYogaForm = document.getElementById('add-yoga-form');
  if (addYogaForm) {
    addYogaForm.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const service = {
        name: document.getElementById('yoga-name').value,
        duration: parseInt(document.getElementById('yoga-duration').value),
        price: parseInt(document.getElementById('yoga-price').value),
        type: document.getElementById('yoga-type').value,
        description: document.getElementById('yoga-description').value
      };
      
      const services = getStoredData('btb_yoga_services') || getDefaultYogaServices();
      services.push(service);
      setStoredData('btb_yoga_services', services);
      
      showStatus('Yoga service added successfully!');
      loadYogaData();
      addYogaForm.reset();
    });
  }
  
  // Save content
  const saveContentBtn = document.getElementById('save-content');
  if (saveContentBtn) {
    saveContentBtn.addEventListener('click', async () => {
      const currentContent = getStoredData('btb_content') || getDefaultContent();
      const content = {
        ...currentContent,
        homepageDescription: document.getElementById('homepage-description').value,
        homepageSubtitle: document.getElementById('homepage-subtitle').value
      };
      
      // Try to save to server first
      try {
        const saved = await saveContentToServer(content);
        if (saved) {
          showStatus('Content saved successfully!');
          return;
        }
      } catch (error) {
        console.log('Server save failed, saving to localStorage');
      }
      
      // Fallback to localStorage
      setStoredData('btb_content', content);
      showStatus('Content saved successfully!');
    });
  }
  
  // Save contact
  const saveContactBtn = document.getElementById('save-contact');
  if (saveContactBtn) {
    saveContactBtn.addEventListener('click', async () => {
      const content = getStoredData('btb_content') || getDefaultContent();
      content.contactPhone = document.getElementById('contact-phone').value;
      content.contactEmail = document.getElementById('contact-email').value;
      content.contactAddress = document.getElementById('contact-address').value;
      
      // Try to save to server first
      try {
        const saved = await saveContentToServer(content);
        if (saved) {
          showStatus('Contact information saved successfully!');
          return;
        }
      } catch (error) {
        console.log('Server save failed, saving to localStorage');
      }
      
      // Fallback to localStorage
      setStoredData('btb_content', content);
      showStatus('Contact information saved successfully!');
    });
  }
}

// Save content to server via API
async function saveContentToServer(content) {
  try {
    const formData = new FormData();
    formData.append('action', 'save_content');
    formData.append('homepage_description', content.homepageDescription || '');
    formData.append('homepage_subtitle', content.homepageSubtitle || '');
    formData.append('contact_phone', content.contactPhone || '');
    formData.append('contact_email', content.contactEmail || '');
    formData.append('contact_address', content.contactAddress || '');
    formData.append('hero_image_url', content.heroImageUrl || '');
    formData.append('hero2_image_url', content.hero2ImageUrl || '');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    return result.success;
  } catch (error) {
    console.error('Error saving to server:', error);
    return false;
  }
}

// Image upload
function initImageUpload() {
  const uploadArea = document.getElementById('image-upload-area');
  const fileInput = document.getElementById('image-upload');
  const previewContainer = document.getElementById('image-preview-container');
  
  if (!uploadArea || !fileInput) return;
  
  uploadArea.addEventListener('click', () => fileInput.click());
  
  uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
  });
  
  uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('dragover');
  });
  
  uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    handleFiles(e.dataTransfer.files);
  });
  
  fileInput.addEventListener('change', (e) => {
    handleFiles(e.target.files);
  });
  
  function handleFiles(files) {
    Array.from(files).forEach(file => {
      if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
          const imageDiv = document.createElement('div');
          imageDiv.style.cssText = 'display: inline-block; margin: 8px; text-align: center;';
          imageDiv.innerHTML = `
            <img src="${e.target.result}" style="max-width: 150px; max-height: 150px; border-radius: 4px; margin-bottom: 8px;">
            <p style="margin: 0; font-size: 12px; color: #4a5568;">${file.name}</p>
          `;
          previewContainer.appendChild(imageDiv);
        };
        reader.readAsDataURL(file);
      }
    });
  }
}

// Initialize floorplan image upload functionality
function initFloorplanImageUpload() {
  const uploadConfigs = [
    {
      buttonId: 'basement-upload-btn',
      inputId: 'basement-image-upload',
      previewId: 'basement-image-preview',
      pathId: 'basement-image-path',
      imageType: 'basement'
    },
    {
      buttonId: 'ground-upload-btn',
      inputId: 'ground-image-upload',
      previewId: 'ground-image-preview',
      pathId: 'ground-image-path',
      imageType: 'ground'
    },
    {
      buttonId: 'loft-upload-btn',
      inputId: 'loft-image-upload',
      previewId: 'loft-image-preview',
      pathId: 'loft-image-path',
      imageType: 'loft'
    }
  ];

  uploadConfigs.forEach(config => {
    const uploadBtn = document.getElementById(config.buttonId);
    const fileInput = document.getElementById(config.inputId);
    const preview = document.getElementById(config.previewId);
    const pathDisplay = document.getElementById(config.pathId);

    if (uploadBtn && fileInput) {
      uploadBtn.addEventListener('click', () => {
        fileInput.click();
      });

      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          await uploadFloorplanImage(file, config.imageType, preview, pathDisplay);
        }
      });
    }
  });
}

// Universal image upload function
// Works for all sections: Floor Plan, Home Page, etc.
// config: { localStorageKey, fieldNameMapper, reloadFunction, imageNameMapper }
async function uploadImage(file, imageType, previewElement, pathElement, config = {}) {
  const {
    localStorageKey = 'btb_floorplan_settings',
    fieldNameMapper = (type) => type + '_image_url',
    reloadFunction = null,
    imageNameMapper = (type) => type.charAt(0).toUpperCase() + type.slice(1)
  } = config;

  const formData = new FormData();
  formData.append('image', file);
  formData.append('image_type', imageType);

  try {
    console.log(`Uploading ${imageType} image:`, file.name);
    
    const response = await fetch('upload_image.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();
    // Some endpoints wrap payload under data; normalize
    const payload = result && result.data ? result.data : result;
    const filepath = payload && payload.filepath ? payload.filepath : '';
    
    if (result.success) {
      console.log('Image uploaded successfully:', result);
      
      // Show preview
      const img = document.createElement('img');
      img.src = filepath + '?v=' + Date.now();
      previewElement.innerHTML = '';
      previewElement.appendChild(img);
      previewElement.style.display = 'block';
      
      // Show path
      pathElement.textContent = filepath;
      pathElement.style.display = 'block';
      
      // Update schematic preview images
      const previewImageMap = {
        'retreat-hero': 'preview-retreat-hero-img',
        'retreat-forest': 'preview-retreat-forest-img',
        'retreat-indoor': 'preview-retreat-indoor-img',
        'retreat-theatre': 'preview-retreat-theatre-img',
        'special-hero': 'preview-special-hero-img',
        'special-pools': 'preview-special-pools-img',
        'special-dining': 'preview-special-dining-img',
        'about-hero': 'preview-about-hero-img',
        'about-founder': 'preview-about-founder-img',
        'about-procter': 'preview-about-procter-img',
        'massage-relaxing': 'preview-massage-relaxing-img',
        'massage-deep-tissue': 'preview-massage-deep-tissue-img',
        'massage-reiki': 'preview-massage-reiki-img',
        'massage-sauna': 'preview-massage-sauna-img',
        'massage-room-booking': 'preview-massage-room-booking-img'
      };
      
      const previewImgId = previewImageMap[imageType];
      if (previewImgId) {
        updatePreviewImage(previewImgId, filepath);
      }
      
      // Update localStorage for immediate site update
      let storedData = localStorage.getItem(localStorageKey);
      let data = {};
      if (storedData) {
        try {
          data = JSON.parse(storedData);
        } catch (e) {
          console.error('Failed to parse localStorage data:', e);
          data = {};
        }
      }
      
      // Update field using mapper
      const fieldName = fieldNameMapper(imageType);
      
      // Special handling for wellness experiences - save in nested structure
      if (localStorageKey === 'btb_wellness_experiences' && imageType.startsWith('wellness-')) {
        const wellnessType = imageType.replace('wellness-', '');
        if (!data[wellnessType]) {
          data[wellnessType] = {};
        }
        data[wellnessType].imageUrl = filepath;
        
        // Also save to btb_content for site display
        let contentData = {};
        const contentStored = localStorage.getItem('btb_content');
        if (contentStored) {
          try {
            contentData = JSON.parse(contentStored);
          } catch (e) {
            console.error('Failed to parse btb_content:', e);
          }
        }
        const wellnessFieldName = 'wellness' + wellnessType.charAt(0).toUpperCase() + wellnessType.slice(1) + 'ImageUrl';
        contentData[wellnessFieldName] = filepath;
        localStorage.setItem('btb_content', JSON.stringify(contentData));
        console.log(`Also saved to btb_content: ${wellnessFieldName} = ${filepath}`);
      } else if (localStorageKey === 'btb_homepage_rooms' && imageType.startsWith('room-') && imageType.endsWith('-card')) {
        // Special handling for room cards - save in nested structure
        const roomType = imageType.replace('room-', '').replace('-card', '');
        // Map to correct keys: basement, groundQueen, groundTwin, second
        let roomKey = roomType;
        if (roomType === 'ground-queen') roomKey = 'groundQueen';
        else if (roomType === 'ground-twin') roomKey = 'groundTwin';
        
        if (!data[roomKey]) {
          data[roomKey] = {};
        }
        data[roomKey].imageUrl = filepath;
        
        // Also save to btb_content for site display
        let contentData = {};
        const contentStored = localStorage.getItem('btb_content');
        if (contentStored) {
          try {
            contentData = JSON.parse(contentStored);
          } catch (e) {
            console.error('Failed to parse btb_content:', e);
          }
        }
        const roomFieldName = 'room' + roomKey.charAt(0).toUpperCase() + roomKey.slice(1) + 'CardImageUrl';
        contentData[roomFieldName] = filepath;
        localStorage.setItem('btb_content', JSON.stringify(contentData));
        console.log(`Also saved to btb_content: ${roomFieldName} = ${filepath}`);
      } else if (localStorageKey === 'btb_massage_images' && imageType.startsWith('massage-')) {
        // Special handling for massage images - save in flat structure
        const massageType = imageType.replace('massage-', '');
        const typeMap = {
          'relaxing': 'relaxing',
          'deep-tissue': 'deepTissue',
          'reiki': 'reiki',
          'sauna': 'sauna',
          'room-booking': 'roomBooking'
        };
        const mappedType = typeMap[massageType] || massageType;
        data[mappedType] = filepath;
        
        // Also save to btb_content for site display
        let contentData = {};
        const contentStored = localStorage.getItem('btb_content');
        if (contentStored) {
          try {
            contentData = JSON.parse(contentStored);
          } catch (e) {
            console.error('Failed to parse btb_content:', e);
          }
        }
        const massageFieldName = 'massage' + mappedType.charAt(0).toUpperCase() + mappedType.slice(1) + 'ImageUrl';
        contentData[massageFieldName] = filepath;
        localStorage.setItem('btb_content', JSON.stringify(contentData));
        console.log(`Also saved to btb_content: ${massageFieldName} = ${filepath}`);
      } else {
        // Standard flat structure
        data[fieldName] = filepath;
        
        // For hero images, also ensure they're in btb_content
        if (localStorageKey === 'btb_content' && (imageType === 'hero' || imageType === 'hero2')) {
          // Already saving to btb_content, no need to duplicate
        }
      }
      
      localStorage.setItem(localStorageKey, JSON.stringify(data));
      console.log(`Updated localStorage (${localStorageKey}) with new image path:`, filepath);
      
      const imageName = imageNameMapper(imageType);
      showStatus(`${imageName} image uploaded successfully!`);
      
      // Force reload of data to get updated paths
      if (reloadFunction && typeof reloadFunction === 'function') {
        setTimeout(() => {
          reloadFunction();
        }, 1000);
      }
      
    } else {
      console.error('Upload failed:', result.error);
      showStatus(`Upload failed: ${result.error}`, 'error');
    }
  } catch (error) {
    console.error('Upload error:', error);
    showStatus('Upload failed: ' + error.message, 'error');
  }
}

// Upload floorplan image function (uses universal function)
async function uploadFloorplanImage(file, imageType, previewElement, pathElement) {
  return uploadImage(file, imageType, previewElement, pathElement, {
    localStorageKey: 'btb_floorplan_settings',
    fieldNameMapper: (type) => type + '_image_url',
    reloadFunction: loadFloorplanData,
    imageNameMapper: (type) => type.charAt(0).toUpperCase() + type.slice(1)
  });
}

// Initialize homepage image upload functionality
function initHomepageImageUpload() {
  // Hero images
  const heroUploadConfigs = [
    {
      buttonId: 'hero-upload-btn',
      inputId: 'hero-image-upload',
      previewId: 'hero-image-preview',
      pathId: 'hero-image-path',
      imageType: 'hero'
    },
    {
      buttonId: 'hero2-upload-btn',
      inputId: 'hero2-image-upload',
      previewId: 'hero2-image-preview',
      pathId: 'hero2-image-path',
      imageType: 'hero2'
    }
  ];

  heroUploadConfigs.forEach(config => {
    const uploadBtn = document.getElementById(config.buttonId);
    const fileInput = document.getElementById(config.inputId);
    const preview = document.getElementById(config.previewId);
    const pathDisplay = document.getElementById(config.pathId);

    if (uploadBtn && fileInput) {
      uploadBtn.addEventListener('click', () => {
        fileInput.click();
      });

      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          await uploadHomepageImage(file, config.imageType, preview, pathDisplay);
        }
      });
    }
  });

  // Floor Plan images
  const floorplanUploadConfigs = [
    {
      buttonId: 'basement-upload-btn',
      inputId: 'basement-image-upload',
      previewId: 'basement-image-preview',
      pathId: 'basement-image-path',
      imageType: 'basement'
    },
    {
      buttonId: 'ground-upload-btn',
      inputId: 'ground-image-upload',
      previewId: 'ground-image-preview',
      pathId: 'ground-image-path',
      imageType: 'ground'
    },
    {
      buttonId: 'loft-upload-btn',
      inputId: 'loft-image-upload',
      previewId: 'loft-image-preview',
      pathId: 'loft-image-path',
      imageType: 'loft'
    }
  ];

  floorplanUploadConfigs.forEach(config => {
    const uploadBtn = document.getElementById(config.buttonId);
    const fileInput = document.getElementById(config.inputId);
    const preview = document.getElementById(config.previewId);
    const pathDisplay = document.getElementById(config.pathId);

    if (uploadBtn && fileInput) {
      uploadBtn.addEventListener('click', () => {
        fileInput.click();
      });

      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          await uploadFloorplanImage(file, config.imageType, preview, pathDisplay);
        }
      });
    }
  });

  // Rooms cards images
  const roomsUploadConfigs = [
    {
      buttonId: 'room-basement-card-upload-btn',
      inputId: 'room-basement-card-upload',
      previewId: 'room-basement-card-preview',
      pathId: 'room-basement-card-path',
      imageType: 'room-basement-card'
    },
    {
      buttonId: 'room-ground-queen-card-upload-btn',
      inputId: 'room-ground-queen-card-upload',
      previewId: 'room-ground-queen-card-preview',
      pathId: 'room-ground-queen-card-path',
      imageType: 'room-ground-queen-card'
    },
    {
      buttonId: 'room-ground-twin-card-upload-btn',
      inputId: 'room-ground-twin-card-upload',
      previewId: 'room-ground-twin-card-preview',
      pathId: 'room-ground-twin-card-path',
      imageType: 'room-ground-twin-card'
    },
    {
      buttonId: 'room-second-card-upload-btn',
      inputId: 'room-second-card-upload',
      previewId: 'room-second-card-preview',
      pathId: 'room-second-card-path',
      imageType: 'room-second-card'
    }
  ];

  roomsUploadConfigs.forEach(config => {
    const uploadBtn = document.getElementById(config.buttonId);
    const fileInput = document.getElementById(config.inputId);
    const preview = document.getElementById(config.previewId);
    const pathDisplay = document.getElementById(config.pathId);

    if (uploadBtn && fileInput) {
      uploadBtn.addEventListener('click', () => {
        fileInput.click();
      });

      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          await uploadImage(file, config.imageType, preview, pathDisplay, {
            localStorageKey: 'btb_homepage_rooms',
            fieldNameMapper: (type) => type.replace('room-', '').replace('-card', '') + 'CardImageUrl',
            reloadFunction: loadHomepageRoomsData,
            imageNameMapper: (type) => type.replace('room-', '').replace('-card', '').charAt(0).toUpperCase() + type.replace('room-', '').replace('-card', '').slice(1) + ' Card'
          });
        }
      });
    }
  });
}

// Upload homepage image function (uses universal function)
async function uploadHomepageImage(file, imageType, previewElement, pathElement) {
  return uploadImage(file, imageType, previewElement, pathElement, {
    localStorageKey: 'btb_content',
    fieldNameMapper: (type) => type === 'hero' ? 'heroImageUrl' : 'hero2ImageUrl',
    reloadFunction: loadHomepageData,
    imageNameMapper: (type) => type === 'hero' ? 'Hero' : 'Hero 2'
  });
}

// ==========================================
// ROOM PAGES MANAGEMENT
// ==========================================

// Load room basement data
async function loadRoomBasementData() {
  console.log('Loading basement room data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const bannerImageUrl = result.data.roomBasementBannerImageUrl || '';
        
        // Update banner image preview
        if (bannerImageUrl) {
          const preview = document.getElementById('basement-banner-preview');
          const pathDisplay = document.getElementById('basement-banner-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = bannerImageUrl + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = bannerImageUrl;
            pathDisplay.style.display = 'block';
          }
        }
        
        // Save to localStorage
        const stored = localStorage.getItem('btb_room_basement') || '{}';
        const storedJson = JSON.parse(stored);
        const roomData = {
          ...storedJson,
          bannerImageUrl: bannerImageUrl || storedJson.bannerImageUrl || ''
        };
        localStorage.setItem('btb_room_basement', JSON.stringify(roomData));
      }
    }
  } catch (error) {
    console.log('Failed to load basement room data:', error);
  }
}

// Initialize basement room image upload
function initRoomBasementImageUpload() {
  const uploadBtn = document.getElementById('basement-banner-upload-btn');
  const fileInput = document.getElementById('basement-banner-upload');
  const preview = document.getElementById('basement-banner-preview');
  const pathDisplay = document.getElementById('basement-banner-path');

  if (uploadBtn && fileInput) {
    uploadBtn.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (file) {
        await uploadImage(file, 'basement-banner', preview, pathDisplay, {
          localStorageKey: 'btb_room_basement',
          fieldNameMapper: () => 'bannerImageUrl',
          reloadFunction: loadRoomBasementData,
          imageNameMapper: () => 'Basement Banner'
        });
      }
    });
  }
}

// Load room ground queen data
async function loadRoomGroundQueenData() {
  console.log('Loading ground queen room data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const bannerImageUrl = result.data.roomGroundQueenBannerImageUrl || '';
        
        if (bannerImageUrl) {
          const preview = document.getElementById('ground-queen-banner-preview');
          const pathDisplay = document.getElementById('ground-queen-banner-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = bannerImageUrl + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = bannerImageUrl;
            pathDisplay.style.display = 'block';
          }
        }
        
        const stored = localStorage.getItem('btb_room_ground_queen') || '{}';
        const storedJson = JSON.parse(stored);
        const roomData = {
          ...storedJson,
          bannerImageUrl: bannerImageUrl || storedJson.bannerImageUrl || ''
        };
        localStorage.setItem('btb_room_ground_queen', JSON.stringify(roomData));
      }
    }
  } catch (error) {
    console.log('Failed to load ground queen room data:', error);
  }
}

function initRoomGroundQueenImageUpload() {
  const uploadBtn = document.getElementById('ground-queen-banner-upload-btn');
  const fileInput = document.getElementById('ground-queen-banner-upload');
  const preview = document.getElementById('ground-queen-banner-preview');
  const pathDisplay = document.getElementById('ground-queen-banner-path');

  if (uploadBtn && fileInput) {
    uploadBtn.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (file) {
        await uploadImage(file, 'ground-queen-banner', preview, pathDisplay, {
          localStorageKey: 'btb_room_ground_queen',
          fieldNameMapper: () => 'bannerImageUrl',
          reloadFunction: loadRoomGroundQueenData,
          imageNameMapper: () => 'Ground Queen Banner'
        });
      }
    });
  }
}

// Load room ground twin data
async function loadRoomGroundTwinData() {
  console.log('Loading ground twin room data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const bannerImageUrl = result.data.roomGroundTwinBannerImageUrl || '';
        
        if (bannerImageUrl) {
          const preview = document.getElementById('ground-twin-banner-preview');
          const pathDisplay = document.getElementById('ground-twin-banner-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = bannerImageUrl + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = bannerImageUrl;
            pathDisplay.style.display = 'block';
          }
        }
        
        const stored = localStorage.getItem('btb_room_ground_twin') || '{}';
        const storedJson = JSON.parse(stored);
        const roomData = {
          ...storedJson,
          bannerImageUrl: bannerImageUrl || storedJson.bannerImageUrl || ''
        };
        localStorage.setItem('btb_room_ground_twin', JSON.stringify(roomData));
      }
    }
  } catch (error) {
    console.log('Failed to load ground twin room data:', error);
  }
}

function initRoomGroundTwinImageUpload() {
  const uploadBtn = document.getElementById('ground-twin-banner-upload-btn');
  const fileInput = document.getElementById('ground-twin-banner-upload');
  const preview = document.getElementById('ground-twin-banner-preview');
  const pathDisplay = document.getElementById('ground-twin-banner-path');

  if (uploadBtn && fileInput) {
    uploadBtn.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (file) {
        await uploadImage(file, 'ground-twin-banner', preview, pathDisplay, {
          localStorageKey: 'btb_room_ground_twin',
          fieldNameMapper: () => 'bannerImageUrl',
          reloadFunction: loadRoomGroundTwinData,
          imageNameMapper: () => 'Ground Twin Banner'
        });
      }
    });
  }
}

// Load room second data
async function loadRoomSecondData() {
  console.log('Loading second floor room data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const bannerImageUrl = result.data.roomSecondBannerImageUrl || '';
        
        if (bannerImageUrl) {
          const preview = document.getElementById('second-banner-preview');
          const pathDisplay = document.getElementById('second-banner-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = bannerImageUrl + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = bannerImageUrl;
            pathDisplay.style.display = 'block';
          }
        }
        
        const stored = localStorage.getItem('btb_room_second') || '{}';
        const storedJson = JSON.parse(stored);
        const roomData = {
          ...storedJson,
          bannerImageUrl: bannerImageUrl || storedJson.bannerImageUrl || ''
        };
        localStorage.setItem('btb_room_second', JSON.stringify(roomData));
      }
    }
  } catch (error) {
    console.log('Failed to load second floor room data:', error);
  }
}

function initRoomSecondImageUpload() {
  const uploadBtn = document.getElementById('second-banner-upload-btn');
  const fileInput = document.getElementById('second-banner-upload');
  const preview = document.getElementById('second-banner-preview');
  const pathDisplay = document.getElementById('second-banner-path');

  if (uploadBtn && fileInput) {
    uploadBtn.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (file) {
        await uploadImage(file, 'second-banner', preview, pathDisplay, {
          localStorageKey: 'btb_room_second',
          fieldNameMapper: () => 'bannerImageUrl',
          reloadFunction: loadRoomSecondData,
          imageNameMapper: () => 'Second Floor Banner'
        });
      }
    });
  }
}

// ==========================================
// PAGE CONTENT MANAGEMENT
// ==========================================

const retreatDefaultValues = {
  'retreat-hero-title': 'Activities and Practices at Back to Base',
  'retreat-hero-subtitle': 'Where nature and quiet become part of your practice',
  'retreat-intro-text': 'Back to Base is a place where nature and quiet become part of your practice. Everything here is designed so that any activity — from yoga to a small creative workshop — takes place in an atmosphere of calm, depth, and inspiration.',
  'retreat-locations-title': 'Our locations for your workshops',
  'retreat-forest-title': 'Forest platforms by the creek',
  'retreat-forest-description': 'Just a few steps from the house, a winding path leads into the forest, where wooden platforms are hidden among tall trees. The air feels lighter here, the sound of the creek creates a natural meditation, and the soft light filtering through the canopy makes every practice deeper.',
  "retreat-forest-list-label": "It's an ideal spot for:",
  'retreat-forest-list-items': 'Sunrise yoga\nEvening meditations\nBreathwork\nAny activity that benefits from a strong connection to nature',
  'retreat-indoor-title': 'Warm, bright indoor space at Back to Base',
  'retreat-indoor-description': 'Inside the house, there is a spacious room with large windows filled with light, warmth, and a sense of comfort — perfect for group gatherings, mini-lectures, workshops, breathwork sessions, or yoga during cooler weather.',
  'retreat-indoor-additional': 'And if you need a more intimate atmosphere or plan to use visual materials, the room can easily be darkened with blackout curtains.',
  'retreat-theatre-title': 'Cozy mini home theatre',
  'retreat-theatre-description': 'For presentations, educational films, documentaries, or shared viewing sessions, we offer a small but very cozy home theatre. Soft lighting, quality sound, and a calm environment help create a fully immersive experience.',
  'retreat-contact-title': 'Are you looking for a place to retreat or interested in joining a workshop?',
  'retreat-contact-text': 'Just send us a message with your preferences, and we will create a program tailored specifically for you!',
  'retreat-organizer-title': 'Are you a yoga instructor or an event organizer looking for a place to host your sessions?',
  'retreat-workshops-title': 'What workshops are our spaces suitable for?',
  'retreat-workshops-intro': 'The indoor spaces, forest platforms, and the forest itself are ideal for the following practices:',
  'retreat-workshops-list': 'Group and private yoga sessions\nMeditations and mindfulness practices\nSound healing and breathwork\nCreative and educational workshops\nMini-lectures and intimate gatherings',
  'retreat-workshops-conclusion': 'Create memorable retreat experiences for your community. Nature here is not just a backdrop — it becomes a full participant. People open up more easily, rest more deeply, and return to themselves more naturally.',
  'retreat-collaboration-title': 'Invitation to Collaborate',
  'retreat-collaboration-intro': 'Back to Base welcomes those who create transformative practices and help people heal and restore.\nWe are looking for:',
  'retreat-collaboration-list': 'Program creators\nYoga instructors\nMeditation teachers\nMassage therapists\nReiki practitioners\nAcupuncturists\nBody-oriented specialists',
  'retreat-collaboration-conclusion': 'If you want to share your work in the quiet of the forest beside a mountain lake, we would be happy to collaborate with you.\nJust call or message us!'
};

const retreatFieldChecklist = [
  { id: 'retreat-hero-title', label: 'Hero title' },
  { id: 'retreat-hero-subtitle', label: 'Hero subtitle' },
  { id: 'retreat-intro-text', label: 'Introduction' },
  { id: 'retreat-forest-description', label: 'Forest description' },
  { id: 'retreat-indoor-description', label: 'Indoor description' },
  { id: 'retreat-theatre-description', label: 'Theatre description' },
  { id: 'retreat-contact-text', label: 'Contact text' },
  { id: 'retreat-workshops-list', label: 'Workshops list' },
  { id: 'retreat-collaboration-list', label: 'Collaboration list' }
];

let retreatHelperInitialized = false;

function getRetreatFieldValue(id) {
  const el = document.getElementById(id);
  if (!el) return '';
  return Object.prototype.hasOwnProperty.call(el, 'value') ? (el.value || '') : (el.textContent || '');
}

function setRetreatFieldValue(el, value) {
  if (!el) return;
  if (Object.prototype.hasOwnProperty.call(el, 'value')) {
    el.value = value;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  } else {
    el.textContent = value;
  }
}

function applyRetreatDefaults(options = {}) {
  const onlyEmpty = options.onlyEmpty !== false;
  let updated = 0;
  Object.entries(retreatDefaultValues).forEach(([id, defaultValue]) => {
    const el = document.getElementById(id);
    if (!el) return;
    const current = getRetreatFieldValue(id).trim();
    if (onlyEmpty && current) {
      return;
    }
    setRetreatFieldValue(el, defaultValue);
    updated++;
  });
  updateRetreatContentStatus();
  return updated;
}

function updateRetreatContentStatus() {
  const statusEl = document.getElementById('retreat-content-status');
  if (!statusEl) return;
  const missing = retreatFieldChecklist.filter(field => !getRetreatFieldValue(field.id).trim());
  if (!missing.length) {
    statusEl.textContent = 'All key sections look complete';
    statusEl.className = 'status-badge success';
    return;
  }
  const preview = missing.slice(0, 3).map(field => field.label).join(', ');
  const remainder = missing.length > 3 ? ` +${missing.length - 3}` : '';
  statusEl.textContent = `Missing text: ${preview}${remainder}`;
  statusEl.className = 'status-badge warning';
}

function initRetreatHelperUI() {
  if (retreatHelperInitialized) {
    updateRetreatContentStatus();
    return;
  }
  const section = document.getElementById('retreat-workshop-section');
  if (!section) return;
  retreatHelperInitialized = true;
  
  const fillBtn = document.getElementById('retreat-fill-missing');
  if (fillBtn) {
    fillBtn.addEventListener('click', (event) => {
      const fillAll = event.shiftKey;
      if (fillAll) {
        const confirmed = window.confirm('Replace all Retreats and Workshops texts with the default story? This cannot be undone.');
        if (!confirmed) return;
      }
      const updated = applyRetreatDefaults({ onlyEmpty: !fillAll });
      if (typeof showStatus === 'function') {
        if (updated === 0) {
          showStatus(fillAll ? 'Default copy already applied to every field.' : 'All fields already contain text.');
        } else if (fillAll) {
          showStatus('Retreat copy reset to the default story for every section.');
        } else {
          showStatus(`Filled ${updated} empty field${updated === 1 ? '' : 's'} with the default story.`);
        }
      }
    });
  }
  
  const previewBtn = document.getElementById('retreat-scroll-preview');
  if (previewBtn) {
    previewBtn.addEventListener('click', () => {
      const preview = document.querySelector('#retreat-workshop-section .schematic-preview');
      if (preview) {
        preview.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  }
  
  section.querySelectorAll('input, textarea').forEach(input => {
    input.addEventListener('input', updateRetreatContentStatus);
  });
  
  updateRetreatContentStatus();
}

// Load retreat and workshop data
async function loadRetreatWorkshopData() {
  console.log('Loading retreat and workshop data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const data = result.data;
        
        // Hero section
        document.getElementById('retreat-hero-title').value = data.retreatHeroTitle || '';
        document.getElementById('retreat-hero-subtitle').value = data.retreatHeroSubtitle || '';
        
        // Introduction
        document.getElementById('retreat-intro-text').value = data.retreatIntroText || '';
        
        // Locations section
        document.getElementById('retreat-locations-title').value = data.retreatLocationsTitle || '';
        
        // Forest Platforms card
        document.getElementById('retreat-forest-title').value = data.retreatForestTitle || '';
        document.getElementById('retreat-forest-description').value = data.retreatForestDescription || '';
        document.getElementById('retreat-forest-list-label').value = data.retreatForestListLabel || '';
        document.getElementById('retreat-forest-list-items').value = data.retreatForestListItems || '';
        
        // Indoor Space card
        document.getElementById('retreat-indoor-title').value = data.retreatIndoorTitle || '';
        document.getElementById('retreat-indoor-description').value = data.retreatIndoorDescription || '';
        document.getElementById('retreat-indoor-additional').value = data.retreatIndoorAdditional || '';
        
        // Home Theatre card
        document.getElementById('retreat-theatre-title').value = data.retreatTheatreTitle || '';
        document.getElementById('retreat-theatre-description').value = data.retreatTheatreDescription || '';
        
        // Contact Form section
        document.getElementById('retreat-contact-title').value = data.retreatContactTitle || '';
        document.getElementById('retreat-contact-text').value = data.retreatContactText || '';
        
        // Organizer section
        document.getElementById('retreat-organizer-title').value = data.retreatOrganizerTitle || '';
        
        // Workshops section
        document.getElementById('retreat-workshops-title').value = data.retreatWorkshopsTitle || '';
        document.getElementById('retreat-workshops-intro').value = data.retreatWorkshopsIntro || '';
        document.getElementById('retreat-workshops-list').value = data.retreatWorkshopsList || '';
        document.getElementById('retreat-workshops-conclusion').value = data.retreatWorkshopsConclusion || '';
        
        // Collaboration section
        document.getElementById('retreat-collaboration-title').value = data.retreatCollaborationTitle || '';
        document.getElementById('retreat-collaboration-intro').value = data.retreatCollaborationIntro || '';
        document.getElementById('retreat-collaboration-list').value = data.retreatCollaborationList || '';
        document.getElementById('retreat-collaboration-conclusion').value = data.retreatCollaborationConclusion || '';
        
        // Update preview
        updateRetreatPreview(data);
        updateRetreatContentStatus();
        
        // Reset unsaved changes flag after loading
        if (typeof retreatHasUnsavedChanges !== 'undefined') {
          retreatHasUnsavedChanges = false;
        }
        if (typeof updateRetreatSaveStatus === 'function') {
          updateRetreatSaveStatus('', '');
        }
        
        console.log('Retreat and workshop content loaded successfully');
      }
    }
  } catch (error) {
    console.log('Failed to load retreat and workshop data:', error);
  }
}

// Update retreat preview
function updateRetreatPreview(data) {
  // Temporarily disable auto-save flag updates during preview update
  // This prevents the flag from being reset when we sync form fields
  const wasUpdatingPreview = window.retreatUpdatingPreview;
  window.retreatUpdatingPreview = true;
  
  // Hero
  const heroTitleEl = document.getElementById('preview-retreat-hero-title');
  const heroSubtitleEl = document.getElementById('preview-retreat-hero-subtitle');
  const heroImgEl = document.getElementById('preview-retreat-hero-img');
  if (heroTitleEl) {
    heroTitleEl.textContent = data.retreatHeroTitle || 'Activities and Practices at Back to Base';
    // Sync to form
    const formField = document.getElementById('retreat-hero-title');
    if (formField) formField.value = heroTitleEl.textContent;
  }
  if (heroSubtitleEl) {
    heroSubtitleEl.textContent = data.retreatHeroSubtitle || 'Where nature and quiet become part of your practice';
    // Sync to form
    const formField = document.getElementById('retreat-hero-subtitle');
    if (formField) formField.value = heroSubtitleEl.textContent;
  }
  
  // Restore flag update state
  window.retreatUpdatingPreview = wasUpdatingPreview;
  if (heroImgEl && data.retreatHeroImageUrl) {
    heroImgEl.src = data.retreatHeroImageUrl;
    heroImgEl.style.display = 'block';
    heroImgEl.nextElementSibling.style.display = 'none';
  }
  
  // Introduction
  const introEl = document.getElementById('preview-retreat-intro');
  if (introEl) {
    const fullText = data.retreatIntroText || 'Back to Base is a place where nature and quiet become part of your practice...';
    // Show full text in preview (CSS will handle visual truncation)
    introEl.textContent = fullText;
    // Sync to form with full text
    const formField = document.getElementById('retreat-intro-text');
    if (formField) formField.value = data.retreatIntroText || '';
  }
  
  // Locations title
  const locationsTitleEl = document.getElementById('preview-retreat-locations-title');
  if (locationsTitleEl) locationsTitleEl.textContent = data.retreatLocationsTitle || 'Our locations for your workshops';
  
  // Forest Platforms
  const forestTitleEl = document.getElementById('preview-retreat-forest-title');
  const forestDescEl = document.getElementById('preview-retreat-forest-desc');
  const forestLabelEl = document.getElementById('preview-retreat-forest-label');
  const forestListEl = document.getElementById('preview-retreat-forest-list');
  const forestImgEl = document.getElementById('preview-retreat-forest-img');
  if (forestTitleEl) {
    forestTitleEl.textContent = data.retreatForestTitle || 'Forest platforms by the creek';
    const formField = document.getElementById('retreat-forest-title');
    if (formField) formField.value = forestTitleEl.textContent;
  }
  if (forestDescEl) {
    const fullText = data.retreatForestDescription || 'Just a few steps from the house...';
    // Show full text in preview (CSS will handle visual truncation)
    forestDescEl.textContent = fullText;
    const formField = document.getElementById('retreat-forest-description');
    if (formField) formField.value = data.retreatForestDescription || '';
  }
  if (forestLabelEl) {
    forestLabelEl.textContent = data.retreatForestListLabel || "It's an ideal spot for:";
    const formField = document.getElementById('retreat-forest-list-label');
    if (formField) formField.value = forestLabelEl.textContent;
  }
  if (forestListEl && data.retreatForestListItems) {
    const items = data.retreatForestListItems.split('\n').filter(item => item.trim());
    forestListEl.innerHTML = items.map(item => `<li>${item.trim()}</li>`).join('');
    const formField = document.getElementById('retreat-forest-list-items');
    if (formField) formField.value = data.retreatForestListItems;
  }
  if (forestImgEl && data.retreatForestImageUrl) {
    forestImgEl.src = data.retreatForestImageUrl;
    forestImgEl.style.display = 'block';
    forestImgEl.nextElementSibling.style.display = 'none';
  }
  
  // Indoor Space
  const indoorTitleEl = document.getElementById('preview-retreat-indoor-title');
  const indoorDescEl = document.getElementById('preview-retreat-indoor-desc');
  const indoorAddEl = document.getElementById('preview-retreat-indoor-add');
  const indoorImgEl = document.getElementById('preview-retreat-indoor-img');
  if (indoorTitleEl) indoorTitleEl.textContent = data.retreatIndoorTitle || 'Warm, bright indoor space at Back to Base';
  // Show full text in preview (CSS will handle visual truncation)
  if (indoorDescEl) indoorDescEl.textContent = data.retreatIndoorDescription || 'Inside the house, there is a spacious room...';
  if (indoorAddEl) indoorAddEl.textContent = data.retreatIndoorAdditional || 'And if you need a more intimate atmosphere...';
  if (indoorImgEl && data.retreatIndoorImageUrl) {
    indoorImgEl.src = data.retreatIndoorImageUrl;
    indoorImgEl.style.display = 'block';
    indoorImgEl.nextElementSibling.style.display = 'none';
  }
  
  // Home Theatre
  const theatreTitleEl = document.getElementById('preview-retreat-theatre-title');
  const theatreDescEl = document.getElementById('preview-retreat-theatre-desc');
  const theatreImgEl = document.getElementById('preview-retreat-theatre-img');
  if (theatreTitleEl) {
    theatreTitleEl.textContent = data.retreatTheatreTitle || 'Cozy mini home theatre';
    const formField = document.getElementById('retreat-theatre-title');
    if (formField) formField.value = theatreTitleEl.textContent;
  }
  if (theatreDescEl) {
    const fullText = data.retreatTheatreDescription || 'For presentations, educational films...';
    // Show full text in preview (CSS will handle visual truncation)
    theatreDescEl.textContent = fullText;
    const formField = document.getElementById('retreat-theatre-description');
    if (formField) formField.value = data.retreatTheatreDescription || '';
  }
  if (theatreImgEl && data.retreatTheatreImageUrl) {
    theatreImgEl.src = data.retreatTheatreImageUrl;
    theatreImgEl.style.display = 'block';
    theatreImgEl.nextElementSibling.style.display = 'none';
  }
  
  // Contact Form
  const contactTitleEl = document.getElementById('preview-retreat-contact-title');
  const contactTextEl = document.getElementById('preview-retreat-contact-text');
  if (contactTitleEl) {
    const fullText = data.retreatContactTitle || 'Are you looking for a place to retreat...';
    // Show full text in preview (CSS will handle visual truncation)
    contactTitleEl.textContent = fullText;
    const formField = document.getElementById('retreat-contact-title');
    if (formField) formField.value = data.retreatContactTitle || '';
  }
  if (contactTextEl) {
    const fullText = data.retreatContactText || 'Just send us a message...';
    // Show full text in preview (CSS will handle visual truncation)
    contactTextEl.textContent = fullText;
    const formField = document.getElementById('retreat-contact-text');
    if (formField) formField.value = data.retreatContactText || '';
  }
  
  // Organizer
  const organizerTitleEl = document.getElementById('preview-retreat-organizer-title');
  if (organizerTitleEl) {
    const fullText = data.retreatOrganizerTitle || 'Are you a yoga instructor...';
    // Show full text in preview (CSS will handle visual truncation)
    organizerTitleEl.textContent = fullText;
    const formField = document.getElementById('retreat-organizer-title');
    if (formField) formField.value = data.retreatOrganizerTitle || '';
  }
  
  // Workshops
  const workshopsTitleEl = document.getElementById('preview-retreat-workshops-title');
  const workshopsIntroEl = document.getElementById('preview-retreat-workshops-intro');
  const workshopsListEl = document.getElementById('preview-retreat-workshops-list');
  const workshopsConclusionEl = document.getElementById('preview-retreat-workshops-conclusion');
  if (workshopsTitleEl) {
    workshopsTitleEl.textContent = data.retreatWorkshopsTitle || 'What workshops are our spaces suitable for?';
    const formField = document.getElementById('retreat-workshops-title');
    if (formField) formField.value = workshopsTitleEl.textContent;
  }
  if (workshopsIntroEl) {
    const fullText = data.retreatWorkshopsIntro || 'The indoor spaces, forest platforms...';
    // Show full text in preview (CSS will handle visual truncation)
    workshopsIntroEl.textContent = fullText;
    const formField = document.getElementById('retreat-workshops-intro');
    if (formField) formField.value = data.retreatWorkshopsIntro || '';
  }
  if (workshopsListEl && data.retreatWorkshopsList) {
    const items = data.retreatWorkshopsList.split('\n').filter(item => item.trim());
    workshopsListEl.innerHTML = items.map(item => `<li>${item.trim()}</li>`).join('');
    const formField = document.getElementById('retreat-workshops-list');
    if (formField) formField.value = data.retreatWorkshopsList;
  }
  if (workshopsConclusionEl) {
    const fullText = data.retreatWorkshopsConclusion || 'Create memorable retreat experiences...';
    // Show full text in preview (CSS will handle visual truncation)
    workshopsConclusionEl.textContent = fullText;
    const formField = document.getElementById('retreat-workshops-conclusion');
    if (formField) formField.value = data.retreatWorkshopsConclusion || '';
  }
  
  // Collaboration
  const collaborationTitleEl = document.getElementById('preview-retreat-collaboration-title');
  const collaborationIntroEl = document.getElementById('preview-retreat-collaboration-intro');
  const collaborationListEl = document.getElementById('preview-retreat-collaboration-list');
  const collaborationConclusionEl = document.getElementById('preview-retreat-collaboration-conclusion');
  if (collaborationTitleEl) {
    collaborationTitleEl.textContent = data.retreatCollaborationTitle || 'Invitation to Collaborate';
    const formField = document.getElementById('retreat-collaboration-title');
    if (formField) formField.value = collaborationTitleEl.textContent;
  }
  if (collaborationIntroEl) {
    const fullText = data.retreatCollaborationIntro || 'Back to Base welcomes those...';
    // Show full text in preview (CSS will handle visual truncation)
    collaborationIntroEl.textContent = fullText;
    const formField = document.getElementById('retreat-collaboration-intro');
    if (formField) formField.value = data.retreatCollaborationIntro || '';
  }
  if (collaborationListEl && data.retreatCollaborationList) {
    const items = data.retreatCollaborationList.split('\n').filter(item => item.trim());
    collaborationListEl.innerHTML = items.map(item => `<li>${item.trim()}</li>`).join('');
    const formField = document.getElementById('retreat-collaboration-list');
    if (formField) formField.value = data.retreatCollaborationList;
  }
  if (collaborationConclusionEl) {
    const fullText = data.retreatCollaborationConclusion || 'If you want to share your work...';
    // Show full text in preview (CSS will handle visual truncation)
    collaborationConclusionEl.textContent = fullText;
    const formField = document.getElementById('retreat-collaboration-conclusion');
    if (formField) formField.value = data.retreatCollaborationConclusion || '';
  }
  
  // Restore flag update state after all form fields are updated
  window.retreatUpdatingPreview = wasUpdatingPreview;
}

// Update a specific field in the retreat preview
// Make it globally accessible for HTML oninput handlers
window.updateRetreatPreviewField = function(fieldKey, value) {
  const fieldMap = {
    'hero-title': { previewId: 'preview-retreat-hero-title', formId: 'retreat-hero-title' },
    'hero-subtitle': { previewId: 'preview-retreat-hero-subtitle', formId: 'retreat-hero-subtitle' },
    'intro-text': { previewId: 'preview-retreat-intro', formId: 'retreat-intro-text' },
    'locations-title': { previewId: 'preview-retreat-locations-title', formId: 'retreat-locations-title' },
    'forest-title': { previewId: 'preview-retreat-forest-title', formId: 'retreat-forest-title' },
    'forest-desc': { previewId: 'preview-retreat-forest-desc', formId: 'retreat-forest-description' },
    'forest-label': { previewId: 'preview-retreat-forest-label', formId: 'retreat-forest-list-label' },
    'forest-list-items': { previewId: 'preview-retreat-forest-list', formId: 'retreat-forest-list-items' },
    'indoor-title': { previewId: 'preview-retreat-indoor-title', formId: 'retreat-indoor-title' },
    'indoor-desc': { previewId: 'preview-retreat-indoor-desc', formId: 'retreat-indoor-description' },
    'indoor-add': { previewId: 'preview-retreat-indoor-add', formId: 'retreat-indoor-additional' },
    'theatre-title': { previewId: 'preview-retreat-theatre-title', formId: 'retreat-theatre-title' },
    'theatre-desc': { previewId: 'preview-retreat-theatre-desc', formId: 'retreat-theatre-description' },
    'contact-title': { previewId: 'preview-retreat-contact-title', formId: 'retreat-contact-title' },
    'contact-text': { previewId: 'preview-retreat-contact-text', formId: 'retreat-contact-text' },
    'organizer-title': { previewId: 'preview-retreat-organizer-title', formId: 'retreat-organizer-title' },
    'workshops-title': { previewId: 'preview-retreat-workshops-title', formId: 'retreat-workshops-title' },
    'workshops-intro': { previewId: 'preview-retreat-workshops-intro', formId: 'retreat-workshops-intro' },
    'workshops-list': { previewId: 'preview-retreat-workshops-list', formId: 'retreat-workshops-list' },
    'workshops-conclusion': { previewId: 'preview-retreat-workshops-conclusion', formId: 'retreat-workshops-conclusion' },
    'collaboration-title': { previewId: 'preview-retreat-collaboration-title', formId: 'retreat-collaboration-title' },
    'collaboration-intro': { previewId: 'preview-retreat-collaboration-intro', formId: 'retreat-collaboration-intro' },
    'collaboration-list': { previewId: 'preview-retreat-collaboration-list', formId: 'retreat-collaboration-list' },
    'collaboration-conclusion': { previewId: 'preview-retreat-collaboration-conclusion', formId: 'retreat-collaboration-conclusion' }
  };
  
  const mapping = fieldMap[fieldKey];
  if (!mapping) {
    console.warn(`Unknown field key: ${fieldKey}`);
    return;
  }
  
  const previewEl = document.getElementById(mapping.previewId);
  if (previewEl) {
    // Handle list items
    if (previewEl.tagName === 'UL' || previewEl.tagName === 'OL') {
      const items = value.split('\n').filter(item => item.trim());
      previewEl.innerHTML = items.map(item => `<li>${item.trim()}</li>`).join('');
    } else {
      // Show full text in preview (CSS will handle visual truncation)
      previewEl.textContent = value;
    }
  }
  
  // Trigger auto-save if retreat section is active
  // Use window.scheduleRetreatAutoSave to ensure it's accessible
  if (typeof window.scheduleRetreatAutoSave === 'function') {
    if (typeof retreatHasUnsavedChanges !== 'undefined') {
      retreatHasUnsavedChanges = true;
    }
    console.log('Calling scheduleRetreatAutoSave from updateRetreatPreviewField');
    window.scheduleRetreatAutoSave();
  } else if (typeof scheduleRetreatAutoSave === 'function') {
    // Fallback to local function if window version not available
    if (typeof retreatHasUnsavedChanges !== 'undefined') {
      retreatHasUnsavedChanges = true;
    }
    scheduleRetreatAutoSave();
  } else {
    console.warn('scheduleRetreatAutoSave is not defined in updateRetreatPreviewField');
  }
};

// Load retreat images data
async function loadRetreatImagesData() {
  console.log('Loading retreat images data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const heroImageUrl = result.data.retreatHeroImageUrl || '';
        const forestImageUrl = result.data.retreatForestImageUrl || '';
        const indoorImageUrl = result.data.retreatIndoorImageUrl || '';
        const theatreImageUrl = result.data.retreatTheatreImageUrl || '';
        
        // Update previews
        updateImagePreview('retreat-hero', heroImageUrl);
        updateImagePreview('retreat-forest', forestImageUrl);
        updateImagePreview('retreat-indoor', indoorImageUrl);
        updateImagePreview('retreat-theatre', theatreImageUrl);
        
        // Save to localStorage
        const stored = localStorage.getItem('btb_retreat_images') || '{}';
        const storedJson = JSON.parse(stored);
        const retreatImagesData = {
          ...storedJson,
          hero: heroImageUrl || storedJson.hero || '',
          forest: forestImageUrl || storedJson.forest || '',
          indoor: indoorImageUrl || storedJson.indoor || '',
          theatre: theatreImageUrl || storedJson.theatre || ''
        };
        localStorage.setItem('btb_retreat_images', JSON.stringify(retreatImagesData));
        console.log('Retreat images data saved to localStorage');
      }
    }
  } catch (error) {
    console.log('Failed to load retreat images data:', error);
  }
}

// Helper function to update image preview
function updateImagePreview(prefix, imageUrl) {
  if (imageUrl) {
    const preview = document.getElementById(prefix + '-preview');
    const pathDisplay = document.getElementById(prefix + '-path');
    if (preview && pathDisplay) {
      const img = document.createElement('img');
      img.src = imageUrl + '?v=' + Date.now();
      preview.innerHTML = '';
      preview.appendChild(img);
      preview.style.display = 'block';
      pathDisplay.textContent = imageUrl;
      pathDisplay.style.display = 'block';
    }
  }
}

// Sync preview content to form field
function syncPreviewToForm(previewElement, fieldId) {
  const formField = document.getElementById(fieldId);
  if (formField && previewElement) {
    let content = previewElement.textContent || previewElement.innerText || '';
    
    // Handle list items - convert to newline-separated format
    if (previewElement.tagName === 'UL' || previewElement.tagName === 'OL') {
      const items = Array.from(previewElement.querySelectorAll('li')).map(li => li.textContent.trim()).filter(item => item);
      content = items.join('\n');
    } else {
      content = content.trim();
    }
    
    const oldValue = formField.value;
    
    // Only trigger auto-save if value actually changed
    if (oldValue !== content) {
      formField.value = content;
      
      // Mark as having unsaved changes BEFORE triggering input event
      // This ensures the flag is set before any event handlers run
      if (fieldId.startsWith('retreat-')) {
        if (typeof retreatHasUnsavedChanges !== 'undefined') {
          retreatHasUnsavedChanges = true;
          console.log('Set retreatHasUnsavedChanges = true for field:', fieldId, 'oldValue:', oldValue.substring(0, 30), 'newValue:', content.substring(0, 30));
        }
      }
      
      // Trigger input event to update other previews if needed
      // This might trigger handlers that check retreatHasUnsavedChanges, so we set it first
      formField.dispatchEvent(new Event('input', { bubbles: true }));
      
      // Schedule auto-save for retreat section
      if (fieldId.startsWith('retreat-')) {
        // Use window.scheduleRetreatAutoSave to ensure it's accessible
        if (typeof window.scheduleRetreatAutoSave === 'function') {
          console.log('Scheduling auto-save for field:', fieldId);
          window.scheduleRetreatAutoSave();
        } else if (typeof scheduleRetreatAutoSave === 'function') {
          // Fallback to local function
          scheduleRetreatAutoSave();
        } else {
          console.warn('scheduleRetreatAutoSave is not defined');
        }
      }
      
      console.log(`Synced preview to form: ${fieldId} = "${content.substring(0, 50)}..."`);
    } else {
      // Value hasn't changed, but if we're here from onblur, we might still need to ensure flag is set
      // This handles the case where the value was already synced but user is still editing
      if (fieldId.startsWith('retreat-')) {
        // Check if there are any pending changes by comparing preview content with form value
        const previewContent = previewElement.textContent || previewElement.innerText || '';
        const formValue = formField.value || '';
        if (previewContent.trim() !== formValue.trim()) {
          // There's a mismatch, sync it
          formField.value = previewContent.trim();
          if (typeof retreatHasUnsavedChanges !== 'undefined') {
            retreatHasUnsavedChanges = true;
            console.log('Set retreatHasUnsavedChanges = true for field (mismatch detected):', fieldId);
            // Schedule auto-save
            if (typeof window.scheduleRetreatAutoSave === 'function') {
              window.scheduleRetreatAutoSave();
            }
          }
        } else {
          console.log('No change detected for field:', fieldId, 'preview:', previewContent.substring(0, 30), 'form:', formValue.substring(0, 30));
        }
      }
    }
  } else {
    console.error(`Failed to sync: formField=${!!formField}, previewElement=${!!previewElement}, fieldId=${fieldId}`);
  }
}

// Show image edit button on hover
function showImageEditButton(container) {
  const btn = container.querySelector('.image-edit-btn');
  if (btn) {
    btn.style.display = 'block';
  }
}

// Hide image edit button on mouse leave
function hideImageEditButton(container) {
  const btn = container.querySelector('.image-edit-btn');
  if (btn) {
    btn.style.display = 'none';
  }
}

// Trigger image upload
function triggerImageUpload(inputId) {
  const fileInput = document.getElementById(inputId);
  if (fileInput) {
    fileInput.click();
  }
}

// Initialize retreat image upload
function initRetreatImageUpload() {
  const uploadConfigs = [
    { buttonId: 'retreat-hero-upload-btn', inputId: 'retreat-hero-upload', previewId: 'retreat-hero-preview', pathId: 'retreat-hero-path', imageType: 'retreat-hero' },
    { buttonId: 'retreat-forest-upload-btn', inputId: 'retreat-forest-upload', previewId: 'retreat-forest-preview', pathId: 'retreat-forest-path', imageType: 'retreat-forest' },
    { buttonId: 'retreat-indoor-upload-btn', inputId: 'retreat-indoor-upload', previewId: 'retreat-indoor-preview', pathId: 'retreat-indoor-path', imageType: 'retreat-indoor' },
    { buttonId: 'retreat-theatre-upload-btn', inputId: 'retreat-theatre-upload', previewId: 'retreat-theatre-preview', pathId: 'retreat-theatre-path', imageType: 'retreat-theatre' }
  ];

  uploadConfigs.forEach(config => {
    const uploadBtn = document.getElementById(config.buttonId);
    const fileInput = document.getElementById(config.inputId);
    const preview = document.getElementById(config.previewId);
    const pathDisplay = document.getElementById(config.pathId);

    if (uploadBtn && fileInput) {
      uploadBtn.addEventListener('click', () => fileInput.click());
      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          // Update preview image immediately
          const previewImgId = 'preview-' + config.imageType.replace('-', '-') + '-img';
          const previewImg = document.getElementById(previewImgId);
          if (previewImg) {
            const reader = new FileReader();
            reader.onload = (event) => {
              previewImg.src = event.target.result;
              previewImg.style.display = 'block';
              const placeholder = previewImg.nextElementSibling;
              if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
            };
            reader.readAsDataURL(file);
          }
          
          await uploadImage(file, config.imageType, preview, pathDisplay, {
            localStorageKey: 'btb_retreat_images',
            fieldNameMapper: (type) => type.replace('retreat-', ''),
            reloadFunction: loadRetreatImagesData,
            imageNameMapper: (type) => {
              const nameMap = {
                'retreat-hero': 'Hero',
                'retreat-forest': 'Forest Platforms',
                'retreat-indoor': 'Indoor Space',
                'retreat-theatre': 'Home Theatre'
              };
              return nameMap[type] || type;
            }
          });
        }
      });
    }
  });
}

// Initialize save handler for retreat images
function initRetreatSaveHandler() {
  const saveBtn = document.getElementById('save-retreat-images');
  if (saveBtn) {
    saveBtn.addEventListener('click', async () => {
      const heroPath = document.getElementById('retreat-hero-path')?.textContent || '';
      const forestPath = document.getElementById('retreat-forest-path')?.textContent || '';
      const indoorPath = document.getElementById('retreat-indoor-path')?.textContent || '';
      const theatrePath = document.getElementById('retreat-theatre-path')?.textContent || '';
      
      const stored = localStorage.getItem('btb_content') || '{}';
      let contentData = {};
      try {
        contentData = JSON.parse(stored);
      } catch (e) {
        console.error('Failed to parse btb_content:', e);
      }
      
      contentData.retreatHeroImageUrl = heroPath;
      contentData.retreatForestImageUrl = forestPath;
      contentData.retreatIndoorImageUrl = indoorPath;
      contentData.retreatTheatreImageUrl = theatrePath;
      
      localStorage.setItem('btb_content', JSON.stringify(contentData));
      
      try {
        const formData = new FormData();
        formData.append('action', 'save_content');
        formData.append('retreat_hero_image_url', heroPath);
        formData.append('retreat_forest_image_url', forestPath);
        formData.append('retreat_indoor_image_url', indoorPath);
        formData.append('retreat_theatre_image_url', theatrePath);
        
        const response = await fetch('api.php', {
          method: 'POST',
          body: formData
        });
        
        if (response.ok) {
          showStatus('Retreat images saved successfully!');
        } else {
          showStatus('Retreat images saved to localStorage. Server save may have failed.', 'warning');
        }
      } catch (error) {
        console.error('Error saving retreat images:', error);
        showStatus('Retreat images saved to localStorage. Server save failed.', 'warning');
      }
    });
  }
}

// Load special data
async function loadSpecialData() {
  console.log('Loading special page data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const data = result.data;
        
        // Hero section
        document.getElementById('special-hero-title').value = data.specialHeroTitle || '';
        document.getElementById('special-hero-subtitle').value = data.specialHeroSubtitle || '';
        
        // Mineral-Rich Pools & Limestone Cave card
        document.getElementById('special-pools-title').value = data.specialPoolsTitle || '';
        document.getElementById('special-pools-description-1').value = data.specialPoolsDescription1 || '';
        document.getElementById('special-pools-description-2').value = data.specialPoolsDescription2 || '';
        
        // Dining & Spa Experience card
        document.getElementById('special-dining-title').value = data.specialDiningTitle || '';
        document.getElementById('special-dining-description-1').value = data.specialDiningDescription1 || '';
        document.getElementById('special-dining-description-2').value = data.specialDiningDescription2 || '';
        
        // Exclusive Offer card
        document.getElementById('special-offer-title').value = data.specialOfferTitle || '';
        document.getElementById('special-offer-main-text').value = data.specialOfferMainText || '';
        document.getElementById('special-offer-description').value = data.specialOfferDescription || '';
        
        // Update preview
        updateSpecialPreview(data);
        
        console.log('Special page content loaded successfully');
      }
    }
  } catch (error) {
    console.log('Failed to load special page data:', error);
  }
}

// Update special preview
function updateSpecialPreview(data) {
  // Hero
  const heroTitleEl = document.getElementById('preview-special-hero-title');
  const heroSubtitleEl = document.getElementById('preview-special-hero-subtitle');
  const heroImgEl = document.getElementById('preview-special-hero-img');
  if (heroTitleEl) heroTitleEl.textContent = data.specialHeroTitle || 'Soak & Savor at Ainsworth Hot Springs';
  if (heroSubtitleEl) heroSubtitleEl.textContent = (data.specialHeroSubtitle || 'Back to Base offers its guests...').substring(0, 60) + '...';
  if (heroImgEl && data.specialHeroImageUrl) {
    heroImgEl.src = data.specialHeroImageUrl;
    heroImgEl.style.display = 'block';
    heroImgEl.nextElementSibling.style.display = 'none';
  }
  
  // Pools
  const poolsTitleEl = document.getElementById('preview-special-pools-title');
  const poolsDescEl = document.getElementById('preview-special-pools-desc');
  const poolsImgEl = document.getElementById('preview-special-pools-img');
  if (poolsTitleEl) poolsTitleEl.textContent = data.specialPoolsTitle || 'Mineral-Rich Pools & Limestone Cave';
  if (poolsDescEl) {
    const desc = (data.specialPoolsDescription1 || '') + ' ' + (data.specialPoolsDescription2 || '');
    poolsDescEl.textContent = desc ? (desc.substring(0, 100) + (desc.length > 100 ? '...' : '')) : 'The Ainsworth Hot Springs are located...';
  }
  if (poolsImgEl && data.specialPoolsImageUrl) {
    poolsImgEl.src = data.specialPoolsImageUrl;
    poolsImgEl.style.display = 'block';
    poolsImgEl.nextElementSibling.style.display = 'none';
  }
  
  // Dining
  const diningTitleEl = document.getElementById('preview-special-dining-title');
  const diningDescEl = document.getElementById('preview-special-dining-desc');
  const diningImgEl = document.getElementById('preview-special-dining-img');
  if (diningTitleEl) diningTitleEl.textContent = data.specialDiningTitle || 'Dining & Spa Experience';
  if (diningDescEl) {
    const desc = (data.specialDiningDescription1 || '') + ' ' + (data.specialDiningDescription2 || '');
    diningDescEl.textContent = desc ? (desc.substring(0, 100) + (desc.length > 100 ? '...' : '')) : 'After your soak, enjoy a meal...';
  }
  if (diningImgEl && data.specialDiningImageUrl) {
    diningImgEl.src = data.specialDiningImageUrl;
    diningImgEl.style.display = 'block';
    diningImgEl.nextElementSibling.style.display = 'none';
  }
  
  // Offer
  const offerTitleEl = document.getElementById('preview-special-offer-title');
  const offerMainEl = document.getElementById('preview-special-offer-main');
  const offerDescEl = document.getElementById('preview-special-offer-desc');
  if (offerTitleEl) offerTitleEl.textContent = data.specialOfferTitle || 'Free Hot Springs Access';
  if (offerMainEl) offerMainEl.textContent = (data.specialOfferMainText || 'Exclusive Offer: Book a minimum 5-night stay...').substring(0, 60) + '...';
  if (offerDescEl) offerDescEl.textContent = (data.specialOfferDescription || 'Book a minimum 5-night stay...').substring(0, 80) + '...';
}

// Update special preview field in real-time
function updateSpecialPreviewField(field, value) {
  const previewMap = {
    'hero-title': 'preview-special-hero-title',
    'hero-subtitle': 'preview-special-hero-subtitle',
    'pools-title': 'preview-special-pools-title',
    'pools-desc': 'preview-special-pools-desc',
    'dining-title': 'preview-special-dining-title',
    'dining-desc': 'preview-special-dining-desc',
    'offer-title': 'preview-special-offer-title',
    'offer-main': 'preview-special-offer-main',
    'offer-desc': 'preview-special-offer-desc'
  };
  
  const previewId = previewMap[field];
  if (previewId) {
    const el = document.getElementById(previewId);
    if (el) {
      if (field === 'hero-subtitle' || field === 'pools-desc' || field === 'dining-desc' || field === 'offer-main' || field === 'offer-desc') {
        el.textContent = value ? (value.substring(0, 100) + (value.length > 100 ? '...' : '')) : '';
      } else {
        el.textContent = value || '';
      }
    }
  }
  
  // Handle combined descriptions
  if (field === 'pools-desc-1' || field === 'pools-desc-2') {
    const desc1 = field === 'pools-desc-1' ? value : document.getElementById('special-pools-description-1')?.value || '';
    const desc2 = field === 'pools-desc-2' ? value : document.getElementById('special-pools-description-2')?.value || '';
    const combined = (desc1 + ' ' + desc2).trim();
    const descEl = document.getElementById('preview-special-pools-desc');
    if (descEl) descEl.textContent = combined ? (combined.substring(0, 100) + (combined.length > 100 ? '...' : '')) : '';
  }
  if (field === 'dining-desc-1' || field === 'dining-desc-2') {
    const desc1 = field === 'dining-desc-1' ? value : document.getElementById('special-dining-description-1')?.value || '';
    const desc2 = field === 'dining-desc-2' ? value : document.getElementById('special-dining-description-2')?.value || '';
    const combined = (desc1 + ' ' + desc2).trim();
    const descEl = document.getElementById('preview-special-dining-desc');
    if (descEl) descEl.textContent = combined ? (combined.substring(0, 100) + (combined.length > 100 ? '...' : '')) : '';
  }
}

// Load special images data
async function loadSpecialImagesData() {
  console.log('Loading special images data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const heroImageUrl = result.data.specialHeroImageUrl || '';
        const poolsImageUrl = result.data.specialPoolsImageUrl || '';
        const diningImageUrl = result.data.specialDiningImageUrl || '';
        
        updateImagePreview('special-hero', heroImageUrl);
        updateImagePreview('special-pools', poolsImageUrl);
        updateImagePreview('special-dining', diningImageUrl);
        
        // Update preview images
        updatePreviewImage('preview-special-hero-img', heroImageUrl);
        updatePreviewImage('preview-special-pools-img', poolsImageUrl);
        updatePreviewImage('preview-special-dining-img', diningImageUrl);
        
        const stored = localStorage.getItem('btb_special_images') || '{}';
        const storedJson = JSON.parse(stored);
        const specialImagesData = {
          ...storedJson,
          hero: heroImageUrl || storedJson.hero || '',
          pools: poolsImageUrl || storedJson.pools || '',
          dining: diningImageUrl || storedJson.dining || ''
        };
        localStorage.setItem('btb_special_images', JSON.stringify(specialImagesData));
        console.log('Special images data saved to localStorage');
      }
    }
  } catch (error) {
    console.log('Failed to load special images data:', error);
  }
}

// Initialize special image upload
function initSpecialImageUpload() {
  const uploadConfigs = [
    { buttonId: 'special-hero-upload-btn', inputId: 'special-hero-upload', previewId: 'special-hero-preview', pathId: 'special-hero-path', imageType: 'special-hero' },
    { buttonId: 'special-pools-upload-btn', inputId: 'special-pools-upload', previewId: 'special-pools-preview', pathId: 'special-pools-path', imageType: 'special-pools' },
    { buttonId: 'special-dining-upload-btn', inputId: 'special-dining-upload', previewId: 'special-dining-preview', pathId: 'special-dining-path', imageType: 'special-dining' }
  ];

  uploadConfigs.forEach(config => {
    const uploadBtn = document.getElementById(config.buttonId);
    const fileInput = document.getElementById(config.inputId);
    const preview = document.getElementById(config.previewId);
    const pathDisplay = document.getElementById(config.pathId);

    if (uploadBtn && fileInput) {
      uploadBtn.addEventListener('click', () => fileInput.click());
      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          await uploadImage(file, config.imageType, preview, pathDisplay, {
            localStorageKey: 'btb_special_images',
            fieldNameMapper: (type) => type.replace('special-', ''),
            reloadFunction: loadSpecialImagesData,
            imageNameMapper: (type) => {
              const nameMap = {
                'special-hero': 'Hero',
                'special-pools': 'Mineral-Rich Pools',
                'special-dining': 'Dining & Spa'
              };
              return nameMap[type] || type;
            }
          });
        }
      });
    }
  });
}

// Initialize save handler for special images
function initSpecialSaveHandler() {
  const saveBtn = document.getElementById('save-special-images');
  if (saveBtn) {
    saveBtn.addEventListener('click', async () => {
      const heroPath = document.getElementById('special-hero-path')?.textContent || '';
      const poolsPath = document.getElementById('special-pools-path')?.textContent || '';
      const diningPath = document.getElementById('special-dining-path')?.textContent || '';
      
      const stored = localStorage.getItem('btb_content') || '{}';
      let contentData = {};
      try {
        contentData = JSON.parse(stored);
      } catch (e) {
        console.error('Failed to parse btb_content:', e);
      }
      
      contentData.specialHeroImageUrl = heroPath;
      contentData.specialPoolsImageUrl = poolsPath;
      contentData.specialDiningImageUrl = diningPath;
      
      localStorage.setItem('btb_content', JSON.stringify(contentData));
      
      try {
        const formData = new FormData();
        formData.append('action', 'save_content');
        formData.append('special_hero_image_url', heroPath);
        formData.append('special_pools_image_url', poolsPath);
        formData.append('special_dining_image_url', diningPath);
        
        const response = await fetch('api.php', {
          method: 'POST',
          body: formData
        });
        
        if (response.ok) {
          showStatus('Special images saved successfully!');
        } else {
          showStatus('Special images saved to localStorage. Server save may have failed.', 'warning');
        }
      } catch (error) {
        console.error('Error saving special images:', error);
        showStatus('Special images saved to localStorage. Server save failed.', 'warning');
      }
    });
  }
}

// Load about data
async function loadAboutData() {
  console.log('Loading about page data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const data = result.data;
        
        // Hero section
        document.getElementById('about-hero-title').value = data.aboutHeroTitle || '';
        document.getElementById('about-hero-subtitle').value = data.aboutHeroSubtitle || '';
        
        // Idea and Origins section
        document.getElementById('about-idea-title').value = data.aboutIdeaTitle || '';
        document.getElementById('about-idea-intro').value = data.aboutIdeaIntro || '';
        document.getElementById('about-idea-paragraph-1').value = data.aboutIdeaParagraph1 || '';
        document.getElementById('about-idea-paragraph-2').value = data.aboutIdeaParagraph2 || '';
        document.getElementById('about-idea-paragraph-3').value = data.aboutIdeaParagraph3 || '';
        document.getElementById('about-idea-signature').value = data.aboutIdeaSignature || '';
        
        // How to Find Us section
        document.getElementById('about-location-title').value = data.aboutLocationTitle || '';
        document.getElementById('about-location-paragraph-1').value = data.aboutLocationParagraph1 || '';
        document.getElementById('about-location-paragraph-2').value = data.aboutLocationParagraph2 || '';
        document.getElementById('about-location-paragraph-3').value = data.aboutLocationParagraph3 || '';
        document.getElementById('about-location-paragraph-4').value = data.aboutLocationParagraph4 || '';
        document.getElementById('about-location-coordinates').value = data.aboutLocationCoordinates || '';
        document.getElementById('about-location-deer-warning').value = data.aboutLocationDeerWarning || '';
        
        // About the Location section
        document.getElementById('about-attractions-title').value = data.aboutAttractionsTitle || '';
        document.getElementById('about-attractions-lead').value = data.aboutAttractionsLead || '';
        
        // Attractions cards
        document.getElementById('about-procter-title').value = data.aboutProcterTitle || '';
        document.getElementById('about-procter-distance').value = data.aboutProcterDistance || '';
        document.getElementById('about-procter-description').value = data.aboutProcterDescription || '';
        
        document.getElementById('about-halcyon-title').value = data.aboutHalcyonTitle || '';
        document.getElementById('about-halcyon-distance').value = data.aboutHalcyonDistance || '';
        document.getElementById('about-halcyon-description').value = data.aboutHalcyonDescription || '';
        
        document.getElementById('about-whitewater-title').value = data.aboutWhitewaterTitle || '';
        document.getElementById('about-whitewater-distance').value = data.aboutWhitewaterDistance || '';
        document.getElementById('about-whitewater-description').value = data.aboutWhitewaterDescription || '';
        
        document.getElementById('about-nelson-title').value = data.aboutNelsonTitle || '';
        document.getElementById('about-nelson-distance').value = data.aboutNelsonDistance || '';
        document.getElementById('about-nelson-description').value = data.aboutNelsonDescription || '';
        
        // Provincial Parks section
        document.getElementById('about-parks-title').value = data.aboutParksTitle || '';
        document.getElementById('about-parks-intro').value = data.aboutParksIntro || '';
        document.getElementById('about-parks-list').value = data.aboutParksList || '';
        
        console.log('About page content loaded successfully');
      }
    }
  } catch (error) {
    console.log('Failed to load about page data:', error);
  }
}

// Load about images data
async function loadAboutImagesData() {
  console.log('Loading about images data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const heroImageUrl = result.data.aboutHeroImageUrl || '';
        const founderImageUrl = result.data.aboutFounderImageUrl || '';
        const procterImageUrl = result.data.aboutProcterImageUrl || '';
        
        updateImagePreview('about-hero', heroImageUrl);
        updateImagePreview('about-founder', founderImageUrl);
        updateImagePreview('about-procter', procterImageUrl);
        
        const stored = localStorage.getItem('btb_about_images') || '{}';
        const storedJson = JSON.parse(stored);
        const aboutImagesData = {
          ...storedJson,
          hero: heroImageUrl || storedJson.hero || '',
          founder: founderImageUrl || storedJson.founder || '',
          procter: procterImageUrl || storedJson.procter || ''
        };
        localStorage.setItem('btb_about_images', JSON.stringify(aboutImagesData));
        console.log('About images data saved to localStorage');
      }
    }
  } catch (error) {
    console.log('Failed to load about images data:', error);
  }
}

// Initialize about image upload
function initAboutImageUpload() {
  const uploadConfigs = [
    { buttonId: 'about-hero-upload-btn', inputId: 'about-hero-upload', previewId: 'about-hero-preview', pathId: 'about-hero-path', imageType: 'about-hero' },
    { buttonId: 'about-founder-upload-btn', inputId: 'about-founder-upload', previewId: 'about-founder-preview', pathId: 'about-founder-path', imageType: 'about-founder' },
    { buttonId: 'about-procter-upload-btn', inputId: 'about-procter-upload', previewId: 'about-procter-preview', pathId: 'about-procter-path', imageType: 'about-procter' }
  ];

  uploadConfigs.forEach(config => {
    const uploadBtn = document.getElementById(config.buttonId);
    const fileInput = document.getElementById(config.inputId);
    const preview = document.getElementById(config.previewId);
    const pathDisplay = document.getElementById(config.pathId);

    if (uploadBtn && fileInput) {
      uploadBtn.addEventListener('click', () => fileInput.click());
      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          await uploadImage(file, config.imageType, preview, pathDisplay, {
            localStorageKey: 'btb_about_images',
            fieldNameMapper: (type) => type.replace('about-', ''),
            reloadFunction: loadAboutImagesData,
            imageNameMapper: (type) => {
              const nameMap = {
                'about-hero': 'Hero',
                'about-founder': 'Founder Photo',
                'about-procter': 'Procter Village'
              };
              return nameMap[type] || type;
            }
          });
        }
      });
    }
  });
}

// Initialize save handler for about images
function initAboutSaveHandler() {
  const saveBtn = document.getElementById('save-about-images');
  if (saveBtn) {
    saveBtn.addEventListener('click', async () => {
      const heroPath = document.getElementById('about-hero-path')?.textContent || '';
      const founderPath = document.getElementById('about-founder-path')?.textContent || '';
      const procterPath = document.getElementById('about-procter-path')?.textContent || '';
      
      const stored = localStorage.getItem('btb_content') || '{}';
      let contentData = {};
      try {
        contentData = JSON.parse(stored);
      } catch (e) {
        console.error('Failed to parse btb_content:', e);
      }
      
      contentData.aboutHeroImageUrl = heroPath;
      contentData.aboutFounderImageUrl = founderPath;
      contentData.aboutProcterImageUrl = procterPath;
      
      localStorage.setItem('btb_content', JSON.stringify(contentData));
      
      try {
        const formData = new FormData();
        formData.append('action', 'save_content');
        formData.append('about_hero_image_url', heroPath);
        formData.append('about_founder_image_url', founderPath);
        formData.append('about_procter_image_url', procterPath);
        
        const response = await fetch('api.php', {
          method: 'POST',
          body: formData
        });
        
        if (response.ok) {
          showStatus('About images saved successfully!');
        } else {
          showStatus('About images saved to localStorage. Server save may have failed.', 'warning');
        }
      } catch (error) {
        console.error('Error saving about images:', error);
        showStatus('About images saved to localStorage. Server save failed.', 'warning');
      }
    });
  }
}

// Load contact data
async function loadContactData() {
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        document.getElementById('contact-phone').value = result.data.contactPhone || '';
        document.getElementById('contact-email').value = result.data.contactEmail || '';
        document.getElementById('contact-address').value = result.data.contactAddress || '';
      }
    }
  } catch (error) {
    console.log('Failed to load contact data');
  }
}

// ==========================================
// HOMEPAGE ROOMS CARDS MANAGEMENT
// ==========================================

// Load homepage rooms cards data
async function loadHomepageRoomsData() {
  console.log('Loading homepage rooms cards data...');
  try {
    // First try to load from API
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    let apiData = {};
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        apiData = {
          basement: { imageUrl: result.data.roomBasementCardImageUrl || '' },
          groundQueen: { imageUrl: result.data.roomGroundQueenCardImageUrl || '' },
          groundTwin: { imageUrl: result.data.roomGroundTwinCardImageUrl || '' },
          second: { imageUrl: result.data.roomSecondCardImageUrl || '' }
        };
      }
    }
    
    // Load from localStorage and merge with API data
    const stored = localStorage.getItem('btb_homepage_rooms');
    let data = {};
    if (stored) {
      try {
        data = JSON.parse(stored);
      } catch (e) {
        console.error('Failed to parse localStorage data:', e);
      }
    }
    
    // Merge API data with localStorage data (API takes precedence for images)
    data = {
      basement: { ...data.basement, imageUrl: apiData.basement?.imageUrl || data.basement?.imageUrl || '' },
      groundQueen: { ...data.groundQueen, imageUrl: apiData.groundQueen?.imageUrl || data.groundQueen?.imageUrl || '' },
      groundTwin: { ...data.groundTwin, imageUrl: apiData.groundTwin?.imageUrl || data.groundTwin?.imageUrl || '' },
      second: { ...data.second, imageUrl: apiData.second?.imageUrl || data.second?.imageUrl || '' }
    };
    
    if (Object.keys(data).length > 0) {
      
      // Basement card
      if (data.basement) {
        document.getElementById('room-basement-card-title').value = data.basement.title || '';
        document.getElementById('room-basement-card-description').value = data.basement.description || '';
        document.getElementById('room-basement-card-price').value = data.basement.price || '';
        if (data.basement.imageUrl) {
          const preview = document.getElementById('room-basement-card-preview');
          const pathDisplay = document.getElementById('room-basement-card-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = data.basement.imageUrl + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = data.basement.imageUrl;
            pathDisplay.style.display = 'block';
          }
        }
      }
      
      // Ground Queen card
      if (data.groundQueen) {
        document.getElementById('room-ground-queen-card-title').value = data.groundQueen.title || '';
        document.getElementById('room-ground-queen-card-description').value = data.groundQueen.description || '';
        document.getElementById('room-ground-queen-card-price').value = data.groundQueen.price || '';
        if (data.groundQueen.imageUrl) {
          const preview = document.getElementById('room-ground-queen-card-preview');
          const pathDisplay = document.getElementById('room-ground-queen-card-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = data.groundQueen.imageUrl + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = data.groundQueen.imageUrl;
            pathDisplay.style.display = 'block';
          }
        }
      }
      
      // Ground Twin card
      if (data.groundTwin) {
        document.getElementById('room-ground-twin-card-title').value = data.groundTwin.title || '';
        document.getElementById('room-ground-twin-card-description').value = data.groundTwin.description || '';
        document.getElementById('room-ground-twin-card-price').value = data.groundTwin.price || '';
        if (data.groundTwin.imageUrl) {
          const preview = document.getElementById('room-ground-twin-card-preview');
          const pathDisplay = document.getElementById('room-ground-twin-card-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = data.groundTwin.imageUrl + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = data.groundTwin.imageUrl;
            pathDisplay.style.display = 'block';
          }
        }
      }
      
      // Second floor card
      if (data.second) {
        document.getElementById('room-second-card-title').value = data.second.title || '';
        document.getElementById('room-second-card-description').value = data.second.description || '';
        document.getElementById('room-second-card-price').value = data.second.price || '';
        if (data.second.imageUrl) {
          const preview = document.getElementById('room-second-card-preview');
          const pathDisplay = document.getElementById('room-second-card-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = data.second.imageUrl + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = data.second.imageUrl;
            pathDisplay.style.display = 'block';
          }
        }
      }
    }
  } catch (error) {
    console.log('Failed to load homepage rooms data:', error);
  }
}

// Initialize homepage rooms image upload
function initHomepageRoomsImageUpload() {
  const uploadConfigs = [
    {
      buttonId: 'room-basement-card-upload-btn',
      inputId: 'room-basement-card-upload',
      previewId: 'room-basement-card-preview',
      pathId: 'room-basement-card-path',
      imageType: 'room-basement-card'
    },
    {
      buttonId: 'room-ground-queen-card-upload-btn',
      inputId: 'room-ground-queen-card-upload',
      previewId: 'room-ground-queen-card-preview',
      pathId: 'room-ground-queen-card-path',
      imageType: 'room-ground-queen-card'
    },
    {
      buttonId: 'room-ground-twin-card-upload-btn',
      inputId: 'room-ground-twin-card-upload',
      previewId: 'room-ground-twin-card-preview',
      pathId: 'room-ground-twin-card-path',
      imageType: 'room-ground-twin-card'
    },
    {
      buttonId: 'room-second-card-upload-btn',
      inputId: 'room-second-card-upload',
      previewId: 'room-second-card-preview',
      pathId: 'room-second-card-path',
      imageType: 'room-second-card'
    }
  ];

  uploadConfigs.forEach(config => {
    const uploadBtn = document.getElementById(config.buttonId);
    const fileInput = document.getElementById(config.inputId);
    const preview = document.getElementById(config.previewId);
    const pathDisplay = document.getElementById(config.pathId);

    if (uploadBtn && fileInput) {
      uploadBtn.addEventListener('click', () => fileInput.click());
      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          await uploadImage(file, config.imageType, preview, pathDisplay, {
            localStorageKey: 'btb_homepage_rooms',
            fieldNameMapper: (type) => type.replace('room-', '').replace('-card', '') + 'CardImageUrl',
            reloadFunction: loadHomepageRoomsData,
            imageNameMapper: (type) => type.replace('room-', '').replace('-card', '').charAt(0).toUpperCase() + type.replace('room-', '').replace('-card', '').slice(1) + ' Card'
          });
        }
      });
    }
  });
}

// ==========================================
// WELLNESS EXPERIENCES MANAGEMENT
// ==========================================

// Load wellness experiences data
async function loadWellnessExperiencesData() {
  console.log('Loading wellness experiences data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        // Load wellness images with preview
        const massageImageUrl = result.data.wellnessMassageImageUrl || '';
        const yogaImageUrl = result.data.wellnessYogaImageUrl || '';
        const saunaImageUrl = result.data.wellnessSaunaImageUrl || '';
        
        // Update massage image preview
        if (massageImageUrl) {
          const preview = document.getElementById('wellness-massage-preview');
          const pathDisplay = document.getElementById('wellness-massage-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = massageImageUrl + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = massageImageUrl;
            pathDisplay.style.display = 'block';
          }
        }
        
        // Update yoga image preview
        if (yogaImageUrl) {
          const preview = document.getElementById('wellness-yoga-preview');
          const pathDisplay = document.getElementById('wellness-yoga-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = yogaImageUrl + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = yogaImageUrl;
            pathDisplay.style.display = 'block';
          }
        }
        
        // Update sauna image preview
        if (saunaImageUrl) {
          const preview = document.getElementById('wellness-sauna-preview');
          const pathDisplay = document.getElementById('wellness-sauna-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = saunaImageUrl + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = saunaImageUrl;
            pathDisplay.style.display = 'block';
          }
        }
        
        // Save to localStorage
        const stored = localStorage.getItem('btb_wellness_experiences') || '{}';
        const storedJson = JSON.parse(stored);
        const wellnessData = {
          ...storedJson,
          massage: {
            ...storedJson.massage,
            imageUrl: massageImageUrl || storedJson.massage?.imageUrl || ''
          },
          yoga: {
            ...storedJson.yoga,
            imageUrl: yogaImageUrl || storedJson.yoga?.imageUrl || ''
          },
          sauna: {
            ...storedJson.sauna,
            imageUrl: saunaImageUrl || storedJson.sauna?.imageUrl || ''
          }
        };
        localStorage.setItem('btb_wellness_experiences', JSON.stringify(wellnessData));
        console.log('Wellness experiences data saved to localStorage');
      }
    }
  } catch (error) {
    console.log('Failed to load wellness experiences data:', error);
  }
}

// Initialize wellness experiences image upload
function initWellnessExperiencesImageUpload() {
  const uploadConfigs = [
    {
      buttonId: 'wellness-massage-upload-btn',
      inputId: 'wellness-massage-upload',
      previewId: 'wellness-massage-preview',
      pathId: 'wellness-massage-path',
      imageType: 'wellness-massage'
    },
    {
      buttonId: 'wellness-yoga-upload-btn',
      inputId: 'wellness-yoga-upload',
      previewId: 'wellness-yoga-preview',
      pathId: 'wellness-yoga-path',
      imageType: 'wellness-yoga'
    },
    {
      buttonId: 'wellness-sauna-upload-btn',
      inputId: 'wellness-sauna-upload',
      previewId: 'wellness-sauna-preview',
      pathId: 'wellness-sauna-path',
      imageType: 'wellness-sauna'
    }
  ];

  uploadConfigs.forEach(config => {
    const uploadBtn = document.getElementById(config.buttonId);
    const fileInput = document.getElementById(config.inputId);
    const preview = document.getElementById(config.previewId);
    const pathDisplay = document.getElementById(config.pathId);

    if (uploadBtn && fileInput) {
      uploadBtn.addEventListener('click', () => fileInput.click());
      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          await uploadImage(file, config.imageType, preview, pathDisplay, {
            localStorageKey: 'btb_wellness_experiences',
            fieldNameMapper: (type) => type.replace('wellness-', '') + 'ImageUrl',
            reloadFunction: loadWellnessExperiencesData,
            imageNameMapper: (type) => type.replace('wellness-', '').charAt(0).toUpperCase() + type.replace('wellness-', '').slice(1)
          });
        }
      });
    }
  });
}

// Initialize save handlers for room pages
function initRoomPageSaveHandlers() {
  // Basement
  const saveBasementBtn = document.getElementById('save-room-basement');
  if (saveBasementBtn) {
    saveBasementBtn.addEventListener('click', async () => {
      const stored = localStorage.getItem('btb_room_basement') || '{}';
      const storedJson = JSON.parse(stored);
      const bannerPathEl = document.getElementById('basement-banner-path');
      
      const data = {
        title: document.getElementById('basement-page-title').value,
        subtitle: document.getElementById('basement-page-subtitle').value,
        description: document.getElementById('basement-page-description').value,
        bannerImageUrl: (bannerPathEl && bannerPathEl.textContent) || storedJson.bannerImageUrl || ''
      };
      
      localStorage.setItem('btb_room_basement', JSON.stringify(data));
      showStatus('Basement page saved successfully!');
    });
  }

  // Ground Queen
  const saveGroundQueenBtn = document.getElementById('save-room-ground-queen');
  if (saveGroundQueenBtn) {
    saveGroundQueenBtn.addEventListener('click', async () => {
      const stored = localStorage.getItem('btb_room_ground_queen') || '{}';
      const storedJson = JSON.parse(stored);
      const bannerPathEl = document.getElementById('ground-queen-banner-path');
      
      const data = {
        title: document.getElementById('ground-queen-page-title').value,
        subtitle: document.getElementById('ground-queen-page-subtitle').value,
        description: document.getElementById('ground-queen-page-description').value,
        bannerImageUrl: (bannerPathEl && bannerPathEl.textContent) || storedJson.bannerImageUrl || ''
      };
      
      localStorage.setItem('btb_room_ground_queen', JSON.stringify(data));
      showStatus('Ground Queen page saved successfully!');
    });
  }

  // Ground Twin
  const saveGroundTwinBtn = document.getElementById('save-room-ground-twin');
  if (saveGroundTwinBtn) {
    saveGroundTwinBtn.addEventListener('click', async () => {
      const stored = localStorage.getItem('btb_room_ground_twin') || '{}';
      const storedJson = JSON.parse(stored);
      const bannerPathEl = document.getElementById('ground-twin-banner-path');
      
      const data = {
        title: document.getElementById('ground-twin-page-title').value,
        subtitle: document.getElementById('ground-twin-page-subtitle').value,
        description: document.getElementById('ground-twin-page-description').value,
        bannerImageUrl: (bannerPathEl && bannerPathEl.textContent) || storedJson.bannerImageUrl || ''
      };
      
      localStorage.setItem('btb_room_ground_twin', JSON.stringify(data));
      showStatus('Ground Twin page saved successfully!');
    });
  }

  // Second Floor
  const saveSecondBtn = document.getElementById('save-room-second');
  if (saveSecondBtn) {
    saveSecondBtn.addEventListener('click', async () => {
      const stored = localStorage.getItem('btb_room_second') || '{}';
      const storedJson = JSON.parse(stored);
      const bannerPathEl = document.getElementById('second-banner-path');
      
      const data = {
        title: document.getElementById('second-page-title').value,
        subtitle: document.getElementById('second-page-subtitle').value,
        description: document.getElementById('second-page-description').value,
        bannerImageUrl: (bannerPathEl && bannerPathEl.textContent) || storedJson.bannerImageUrl || ''
      };
      
      localStorage.setItem('btb_room_second', JSON.stringify(data));
      showStatus('Second Floor page saved successfully!');
    });
  }
}

// Initialize save handlers for page content
function initPageContentSaveHandlers() {
  // Retreat and Workshop
  const saveRetreatBtn = document.getElementById('save-retreat-workshop');
  if (saveRetreatBtn) {
    saveRetreatBtn.addEventListener('click', async () => {
      console.log('Saving retreat and workshop content...');
      
      // Ensure preview changes are synced to form before saving
      const heroTitlePreview = document.getElementById('preview-retreat-hero-title');
      const heroTitleForm = document.getElementById('retreat-hero-title');
      if (heroTitlePreview && heroTitleForm) {
        heroTitleForm.value = heroTitlePreview.textContent.trim();
      }
      const heroSubtitlePreview = document.getElementById('preview-retreat-hero-subtitle');
      const heroSubtitleForm = document.getElementById('retreat-hero-subtitle');
      if (heroSubtitlePreview && heroSubtitleForm) {
        heroSubtitleForm.value = heroSubtitlePreview.textContent.trim();
      }
      
      const formData = new FormData();
      formData.append('action', 'save_content');
      
      // Hero section
      const heroTitle = document.getElementById('retreat-hero-title')?.value || '';
      const heroSubtitle = document.getElementById('retreat-hero-subtitle')?.value || '';
      console.log('Hero title:', heroTitle);
      console.log('Hero subtitle:', heroSubtitle);
      formData.append('retreat_hero_title', heroTitle);
      formData.append('retreat_hero_subtitle', heroSubtitle);
      
      // Introduction
      formData.append('retreat_intro_text', document.getElementById('retreat-intro-text')?.value || '');
      
      // Locations section
      formData.append('retreat_locations_title', document.getElementById('retreat-locations-title')?.value || '');
      
      // Forest Platforms card
      formData.append('retreat_forest_title', document.getElementById('retreat-forest-title')?.value || '');
      formData.append('retreat_forest_description', document.getElementById('retreat-forest-description')?.value || '');
      formData.append('retreat_forest_list_label', document.getElementById('retreat-forest-list-label')?.value || '');
      formData.append('retreat_forest_list_items', document.getElementById('retreat-forest-list-items')?.value || '');
      
      // Indoor Space card
      formData.append('retreat_indoor_title', document.getElementById('retreat-indoor-title')?.value || '');
      formData.append('retreat_indoor_description', document.getElementById('retreat-indoor-description')?.value || '');
      formData.append('retreat_indoor_additional', document.getElementById('retreat-indoor-additional')?.value || '');
      
      // Home Theatre card
      formData.append('retreat_theatre_title', document.getElementById('retreat-theatre-title')?.value || '');
      formData.append('retreat_theatre_description', document.getElementById('retreat-theatre-description')?.value || '');
      
      // Contact Form section
      formData.append('retreat_contact_title', document.getElementById('retreat-contact-title')?.value || '');
      formData.append('retreat_contact_text', document.getElementById('retreat-contact-text')?.value || '');
      
      // Organizer section
      formData.append('retreat_organizer_title', document.getElementById('retreat-organizer-title')?.value || '');
      
      // Workshops section
      formData.append('retreat_workshops_title', document.getElementById('retreat-workshops-title')?.value || '');
      formData.append('retreat_workshops_intro', document.getElementById('retreat-workshops-intro')?.value || '');
      formData.append('retreat_workshops_list', document.getElementById('retreat-workshops-list')?.value || '');
      formData.append('retreat_workshops_conclusion', document.getElementById('retreat-workshops-conclusion')?.value || '');
      
      // Collaboration section
      formData.append('retreat_collaboration_title', document.getElementById('retreat-collaboration-title')?.value || '');
      formData.append('retreat_collaboration_intro', document.getElementById('retreat-collaboration-intro')?.value || '');
      formData.append('retreat_collaboration_list', document.getElementById('retreat-collaboration-list')?.value || '');
      formData.append('retreat_collaboration_conclusion', document.getElementById('retreat-collaboration-conclusion')?.value || '');
      
      try {
        const response = await fetch('api.php', {
          method: 'POST',
          body: formData
        });
        
        if (response.ok) {
          const result = await response.json();
          console.log('Save response:', result);
          if (result.success) {
            showStatus('Retreat and workshop content saved successfully!');
            
            // Update auto-save status
            if (typeof retreatHasUnsavedChanges !== 'undefined') {
              retreatHasUnsavedChanges = false;
            }
            if (typeof updateRetreatSaveStatus === 'function') {
              updateRetreatSaveStatus('Сохранено', '✓');
              setTimeout(() => {
                if (typeof retreatHasUnsavedChanges !== 'undefined' && !retreatHasUnsavedChanges) {
                  updateRetreatSaveStatus('', '');
                }
              }, 3000);
            }
            
            // Save to localStorage for immediate site update
            const retreatContent = {
              retreatHeroTitle: document.getElementById('retreat-hero-title')?.value || '',
              retreatHeroSubtitle: document.getElementById('retreat-hero-subtitle')?.value || '',
              retreatIntroText: document.getElementById('retreat-intro-text')?.value || '',
              retreatLocationsTitle: document.getElementById('retreat-locations-title')?.value || '',
              retreatForestTitle: document.getElementById('retreat-forest-title')?.value || '',
              retreatForestDescription: document.getElementById('retreat-forest-description')?.value || '',
              retreatForestListLabel: document.getElementById('retreat-forest-list-label')?.value || '',
              retreatForestListItems: document.getElementById('retreat-forest-list-items')?.value || '',
              retreatIndoorTitle: document.getElementById('retreat-indoor-title')?.value || '',
              retreatIndoorDescription: document.getElementById('retreat-indoor-description')?.value || '',
              retreatIndoorAdditional: document.getElementById('retreat-indoor-additional')?.value || '',
              retreatTheatreTitle: document.getElementById('retreat-theatre-title')?.value || '',
              retreatTheatreDescription: document.getElementById('retreat-theatre-description')?.value || '',
              retreatContactTitle: document.getElementById('retreat-contact-title')?.value || '',
              retreatContactText: document.getElementById('retreat-contact-text')?.value || '',
              retreatOrganizerTitle: document.getElementById('retreat-organizer-title')?.value || '',
              retreatWorkshopsTitle: document.getElementById('retreat-workshops-title')?.value || '',
              retreatWorkshopsIntro: document.getElementById('retreat-workshops-intro')?.value || '',
              retreatWorkshopsList: document.getElementById('retreat-workshops-list')?.value || '',
              retreatWorkshopsConclusion: document.getElementById('retreat-workshops-conclusion')?.value || '',
              retreatCollaborationTitle: document.getElementById('retreat-collaboration-title')?.value || '',
              retreatCollaborationIntro: document.getElementById('retreat-collaboration-intro')?.value || '',
              retreatCollaborationList: document.getElementById('retreat-collaboration-list')?.value || '',
              retreatCollaborationConclusion: document.getElementById('retreat-collaboration-conclusion')?.value || ''
            };
            localStorage.setItem('btb_retreat_workshop_content', JSON.stringify(retreatContent));
            console.log('Retreat content saved to localStorage');
            
            // Reload data to refresh preview
            await loadRetreatWorkshopData();
          } else {
            showStatus('Failed to save: ' + (result.error || 'Unknown error'), 'error');
            if (typeof updateRetreatSaveStatus === 'function') {
              updateRetreatSaveStatus('Ошибка сохранения', '❌');
            }
            if (typeof retreatHasUnsavedChanges !== 'undefined') {
              retreatHasUnsavedChanges = true;
            }
          }
        } else {
          showStatus('Failed to save retreat content', 'error');
          if (typeof updateRetreatSaveStatus === 'function') {
            updateRetreatSaveStatus('Ошибка сохранения', '❌');
          }
          if (typeof retreatHasUnsavedChanges !== 'undefined') {
            retreatHasUnsavedChanges = true;
          }
        }
      } catch (error) {
        console.error('Error saving retreat content:', error);
        showStatus('Error saving retreat content: ' + error.message, 'error');
        if (typeof updateRetreatSaveStatus === 'function') {
          updateRetreatSaveStatus('Ошибка сохранения', '❌');
        }
        if (typeof retreatHasUnsavedChanges !== 'undefined') {
          retreatHasUnsavedChanges = true;
        }
      }
    });
  }

// Auto-save for Retreats and Workshops
let retreatAutoSaveTimer = null;
let retreatHasUnsavedChanges = false;
let retreatIsSaving = false;
let retreatSaveRetryCount = 0;
const RETREAT_AUTO_SAVE_DELAY = 2000; // 2 seconds
const RETREAT_MAX_RETRIES = 3;

// Make functions globally accessible
function scheduleRetreatAutoSave() {
  // Ensure flag is set before scheduling
  if (typeof retreatHasUnsavedChanges !== 'undefined') {
    retreatHasUnsavedChanges = true;
    console.log('scheduleRetreatAutoSave: Setting retreatHasUnsavedChanges = true');
  }
  
  // Clear existing timer
  if (retreatAutoSaveTimer) {
    clearTimeout(retreatAutoSaveTimer);
    console.log('scheduleRetreatAutoSave: Cleared existing timer');
  }
  
  // Update status to show pending save
  const updateStatus = window.updateRetreatSaveStatus || updateRetreatSaveStatus;
  if (typeof updateStatus === 'function') {
    updateStatus('Изменения не сохранены', '⏳');
  } else {
    console.warn('updateRetreatSaveStatus is not defined');
  }
  
  // Schedule auto-save
  console.log('Scheduling auto-save in', RETREAT_AUTO_SAVE_DELAY, 'ms, retreatHasUnsavedChanges:', retreatHasUnsavedChanges);
  retreatAutoSaveTimer = setTimeout(() => {
    console.log('Auto-save timer fired, calling autoSaveRetreatContent...');
    console.log('At timer fire, retreatHasUnsavedChanges:', retreatHasUnsavedChanges);
    const autoSave = window.autoSaveRetreatContent || autoSaveRetreatContent;
    if (typeof autoSave === 'function') {
      autoSave();
    } else {
      console.warn('autoSaveRetreatContent is not defined');
    }
  }, RETREAT_AUTO_SAVE_DELAY);
}

// Make it globally accessible
window.scheduleRetreatAutoSave = scheduleRetreatAutoSave;

function initRetreatAutoSave() {
  // Track changes in preview fields (contenteditable)
  const previewFields = document.querySelectorAll('#retreat-workshop-section .editable-preview');
  previewFields.forEach(field => {
    field.addEventListener('input', () => {
      console.log('Preview field input event, setting retreatHasUnsavedChanges = true');
      retreatHasUnsavedChanges = true;
      const schedule = window.scheduleRetreatAutoSave || scheduleRetreatAutoSave;
      if (typeof schedule === 'function') {
        schedule();
      }
    });
    field.addEventListener('blur', () => {
      // Sync to form on blur
      const fieldId = field.getAttribute('data-field');
      if (fieldId) {
        console.log('Preview field blur, syncing to form:', fieldId);
        // Set flag before syncing to ensure it's not lost
        if (fieldId.startsWith('retreat-')) {
          retreatHasUnsavedChanges = true;
          console.log('Set retreatHasUnsavedChanges = true on blur for:', fieldId);
        }
        syncPreviewToForm(field, fieldId);
        // syncPreviewToForm already schedules auto-save, but we've set the flag above
      }
    });
  });
  
  // Track changes in form fields
  const formFields = [
    'retreat-hero-title', 'retreat-hero-subtitle', 'retreat-intro-text',
    'retreat-locations-title', 'retreat-forest-title', 'retreat-forest-description',
    'retreat-forest-list-label', 'retreat-forest-list-items', 'retreat-indoor-title',
    'retreat-indoor-description', 'retreat-indoor-additional', 'retreat-theatre-title',
    'retreat-theatre-description', 'retreat-contact-title', 'retreat-contact-text',
    'retreat-organizer-title', 'retreat-workshops-title', 'retreat-workshops-intro',
    'retreat-workshops-list', 'retreat-workshops-conclusion', 'retreat-collaboration-title',
    'retreat-collaboration-intro', 'retreat-collaboration-list', 'retreat-collaboration-conclusion'
  ];
  
  formFields.forEach(fieldId => {
    const field = document.getElementById(fieldId);
    if (field) {
      field.addEventListener('input', () => {
        retreatHasUnsavedChanges = true;
        scheduleRetreatAutoSave();
      });
    }
  });
  
  // Warn before leaving page with unsaved changes
  // Only add listener once
  if (!window.retreatBeforeUnloadAdded) {
    window.addEventListener('beforeunload', (e) => {
      if (retreatHasUnsavedChanges && !retreatIsSaving) {
        e.preventDefault();
        e.returnValue = 'У вас есть несохраненные изменения. Вы уверены, что хотите покинуть страницу?';
        return e.returnValue;
      }
    });
    window.retreatBeforeUnloadAdded = true;
  }
}

async function autoSaveRetreatContent() {
  console.log('autoSaveRetreatContent called, retreatIsSaving:', retreatIsSaving, 'retreatHasUnsavedChanges:', retreatHasUnsavedChanges);
  
  if (retreatIsSaving) {
    // If already saving, reschedule
    console.log('Already saving, rescheduling...');
    const schedule = window.scheduleRetreatAutoSave || scheduleRetreatAutoSave;
    if (typeof schedule === 'function') {
      schedule();
    }
    return;
  }
  
  // Re-check flag after a small delay to see if it was reset
  // Sometimes the flag gets reset between scheduling and execution
  await new Promise(resolve => setTimeout(resolve, 50));
  console.log('After 50ms delay, retreatHasUnsavedChanges:', retreatHasUnsavedChanges);
  
  if (!retreatHasUnsavedChanges) {
    console.log('No unsaved changes, skipping auto-save. Flag was reset somewhere.');
    console.trace('Stack trace for debugging:');
    return;
  }
  
  console.log('Starting auto-save...');
  retreatIsSaving = true;
  retreatSaveRetryCount = 0;
  const updateStatus = window.updateRetreatSaveStatus || updateRetreatSaveStatus;
  if (typeof updateStatus === 'function') {
    updateStatus('Сохранение...', '⏳');
  }
  
  await saveRetreatContentWithRetry();
  
  retreatIsSaving = false;
  console.log('Auto-save completed');
}

// Make it globally accessible
window.autoSaveRetreatContent = autoSaveRetreatContent;

async function saveRetreatContentWithRetry() {
  console.log('saveRetreatContentWithRetry called, attempt:', retreatSaveRetryCount + 1);
  try {
    // Sync all preview fields to form before saving
    console.log('Syncing preview fields to form...');
    document.querySelectorAll('#retreat-workshop-section .editable-preview').forEach(previewEl => {
      const fieldId = previewEl.getAttribute('data-field');
      if (fieldId) {
        syncPreviewToForm(previewEl, fieldId);
      }
    });
    
    const formData = new FormData();
    formData.append('action', 'save_content');
    
    // Hero section
    formData.append('retreat_hero_title', document.getElementById('retreat-hero-title')?.value || '');
    formData.append('retreat_hero_subtitle', document.getElementById('retreat-hero-subtitle')?.value || '');
    
    // Introduction
    formData.append('retreat_intro_text', document.getElementById('retreat-intro-text')?.value || '');
    
    // Locations section
    formData.append('retreat_locations_title', document.getElementById('retreat-locations-title')?.value || '');
    
    // Forest Platforms card
    formData.append('retreat_forest_title', document.getElementById('retreat-forest-title')?.value || '');
    formData.append('retreat_forest_description', document.getElementById('retreat-forest-description')?.value || '');
    formData.append('retreat_forest_list_label', document.getElementById('retreat-forest-list-label')?.value || '');
    formData.append('retreat_forest_list_items', document.getElementById('retreat-forest-list-items')?.value || '');
    
    // Indoor Space card
    formData.append('retreat_indoor_title', document.getElementById('retreat-indoor-title')?.value || '');
    formData.append('retreat_indoor_description', document.getElementById('retreat-indoor-description')?.value || '');
    formData.append('retreat_indoor_additional', document.getElementById('retreat-indoor-additional')?.value || '');
    
    // Home Theatre card
    formData.append('retreat_theatre_title', document.getElementById('retreat-theatre-title')?.value || '');
    formData.append('retreat_theatre_description', document.getElementById('retreat-theatre-description')?.value || '');
    
    // Contact Form section
    formData.append('retreat_contact_title', document.getElementById('retreat-contact-title')?.value || '');
    formData.append('retreat_contact_text', document.getElementById('retreat-contact-text')?.value || '');
    
    // Organizer section
    formData.append('retreat_organizer_title', document.getElementById('retreat-organizer-title')?.value || '');
    
    // Workshops section
    formData.append('retreat_workshops_title', document.getElementById('retreat-workshops-title')?.value || '');
    formData.append('retreat_workshops_intro', document.getElementById('retreat-workshops-intro')?.value || '');
    formData.append('retreat_workshops_list', document.getElementById('retreat-workshops-list')?.value || '');
    formData.append('retreat_workshops_conclusion', document.getElementById('retreat-workshops-conclusion')?.value || '');
    
    // Collaboration section
    formData.append('retreat_collaboration_title', document.getElementById('retreat-collaboration-title')?.value || '');
    formData.append('retreat_collaboration_intro', document.getElementById('retreat-collaboration-intro')?.value || '');
    formData.append('retreat_collaboration_list', document.getElementById('retreat-collaboration-list')?.value || '');
    formData.append('retreat_collaboration_conclusion', document.getElementById('retreat-collaboration-conclusion')?.value || '');
    
    console.log('Sending save request to api.php...');
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    console.log('Response status:', response.status);
    if (response.ok) {
      const result = await response.json();
      console.log('Save response:', result);
      if (result.success) {
        console.log('Auto-save successful!');
        retreatHasUnsavedChanges = false;
        retreatSaveRetryCount = 0;
        
        // Save to localStorage for immediate site update
        const retreatContent = {
          retreatHeroTitle: document.getElementById('retreat-hero-title')?.value || '',
          retreatHeroSubtitle: document.getElementById('retreat-hero-subtitle')?.value || '',
          retreatIntroText: document.getElementById('retreat-intro-text')?.value || '',
          retreatLocationsTitle: document.getElementById('retreat-locations-title')?.value || '',
          retreatForestTitle: document.getElementById('retreat-forest-title')?.value || '',
          retreatForestDescription: document.getElementById('retreat-forest-description')?.value || '',
          retreatForestListLabel: document.getElementById('retreat-forest-list-label')?.value || '',
          retreatForestListItems: document.getElementById('retreat-forest-list-items')?.value || '',
          retreatIndoorTitle: document.getElementById('retreat-indoor-title')?.value || '',
          retreatIndoorDescription: document.getElementById('retreat-indoor-description')?.value || '',
          retreatIndoorAdditional: document.getElementById('retreat-indoor-additional')?.value || '',
          retreatTheatreTitle: document.getElementById('retreat-theatre-title')?.value || '',
          retreatTheatreDescription: document.getElementById('retreat-theatre-description')?.value || '',
          retreatContactTitle: document.getElementById('retreat-contact-title')?.value || '',
          retreatContactText: document.getElementById('retreat-contact-text')?.value || '',
          retreatOrganizerTitle: document.getElementById('retreat-organizer-title')?.value || '',
          retreatWorkshopsTitle: document.getElementById('retreat-workshops-title')?.value || '',
          retreatWorkshopsIntro: document.getElementById('retreat-workshops-intro')?.value || '',
          retreatWorkshopsList: document.getElementById('retreat-workshops-list')?.value || '',
          retreatWorkshopsConclusion: document.getElementById('retreat-workshops-conclusion')?.value || '',
          retreatCollaborationTitle: document.getElementById('retreat-collaboration-title')?.value || '',
          retreatCollaborationIntro: document.getElementById('retreat-collaboration-intro')?.value || '',
          retreatCollaborationList: document.getElementById('retreat-collaboration-list')?.value || '',
          retreatCollaborationConclusion: document.getElementById('retreat-collaboration-conclusion')?.value || ''
        };
        localStorage.setItem('btb_retreat_workshop_content', JSON.stringify(retreatContent));
        
        console.log('Auto-save successful! Updating status...');
        if (typeof updateRetreatSaveStatus === 'function') {
          updateRetreatSaveStatus('Сохранено', '✓');
          
          // Hide status after 3 seconds
          setTimeout(() => {
            if (!retreatHasUnsavedChanges) {
              updateRetreatSaveStatus('', '');
            }
          }, 3000);
        } else {
          console.error('updateRetreatSaveStatus is not defined!');
        }
        
        // Reload data to refresh preview
        await loadRetreatWorkshopData();
        
        // Re-initialize auto-save after reload (fields might have changed)
        initRetreatAutoSave();
      } else {
        throw new Error(result.error || 'Unknown error');
      }
    } else {
      const errorText = await response.text();
      throw new Error('Server error: ' + errorText);
    }
  } catch (error) {
    console.error('Auto-save error:', error);
    retreatSaveRetryCount++;
    
    if (retreatSaveRetryCount < RETREAT_MAX_RETRIES) {
      updateRetreatSaveStatus(`Ошибка сохранения. Повтор... (${retreatSaveRetryCount}/${RETREAT_MAX_RETRIES})`, '⚠️');
      // Retry after 1 second
      setTimeout(() => {
        saveRetreatContentWithRetry();
      }, 1000);
    } else {
      updateRetreatSaveStatus('Ошибка сохранения', '❌');
      retreatHasUnsavedChanges = true; // Keep flag as true so user knows there are unsaved changes
    }
  }
}

function updateRetreatSaveStatus(text, icon) {
  console.log('updateRetreatSaveStatus called with:', text, icon);
  const statusText = document.getElementById('retreat-save-status-text');
  const statusIcon = document.getElementById('retreat-save-status-icon');
  
  if (!statusText) {
    console.warn('retreat-save-status-text element not found!');
    return;
  }
  
  if (!statusIcon) {
    console.warn('retreat-save-status-icon element not found!');
  }
  
  statusText.textContent = text;
  if (text === 'Сохранено') {
    statusText.style.color = '#10b981';
  } else if (text.includes('Ошибка')) {
    statusText.style.color = '#ef4444';
  } else if (text === 'Сохранение...') {
    statusText.style.color = '#3b82f6';
  } else {
    statusText.style.color = '#6b7280';
  }
  
  if (statusIcon) {
    statusIcon.textContent = icon;
  }
  
  console.log('Status updated:', text);
}

// Make it globally accessible
window.updateRetreatSaveStatus = updateRetreatSaveStatus;

  // Special
  const saveSpecialBtn = document.getElementById('save-special');
  if (saveSpecialBtn) {
    saveSpecialBtn.addEventListener('click', async () => {
      const formData = new FormData();
      formData.append('action', 'save_content');
      
      // Hero section
      formData.append('special_hero_title', document.getElementById('special-hero-title')?.value || '');
      formData.append('special_hero_subtitle', document.getElementById('special-hero-subtitle')?.value || '');
      
      // Mineral-Rich Pools & Limestone Cave card
      formData.append('special_pools_title', document.getElementById('special-pools-title')?.value || '');
      formData.append('special_pools_description_1', document.getElementById('special-pools-description-1')?.value || '');
      formData.append('special_pools_description_2', document.getElementById('special-pools-description-2')?.value || '');
      
      // Dining & Spa Experience card
      formData.append('special_dining_title', document.getElementById('special-dining-title')?.value || '');
      formData.append('special_dining_description_1', document.getElementById('special-dining-description-1')?.value || '');
      formData.append('special_dining_description_2', document.getElementById('special-dining-description-2')?.value || '');
      
      // Exclusive Offer card
      formData.append('special_offer_title', document.getElementById('special-offer-title')?.value || '');
      formData.append('special_offer_main_text', document.getElementById('special-offer-main-text')?.value || '');
      formData.append('special_offer_description', document.getElementById('special-offer-description')?.value || '');
      
      try {
        const response = await fetch('api.php', {
          method: 'POST',
          body: formData
        });
        
        if (response.ok) {
          const result = await response.json();
          if (result.success) {
            showStatus('Special page content saved successfully!');
          } else {
            showStatus('Failed to save: ' + (result.error || 'Unknown error'), 'error');
          }
        } else {
          showStatus('Failed to save special content', 'error');
        }
      } catch (error) {
        console.error('Error saving special content:', error);
        showStatus('Error saving special content: ' + error.message, 'error');
      }
    });
  }

  // About
  const saveAboutBtn = document.getElementById('save-about');
  if (saveAboutBtn) {
    saveAboutBtn.addEventListener('click', async () => {
      const formData = new FormData();
      formData.append('action', 'save_content');
      
      // Hero section
      formData.append('about_hero_title', document.getElementById('about-hero-title')?.value || '');
      formData.append('about_hero_subtitle', document.getElementById('about-hero-subtitle')?.value || '');
      
      // Idea and Origins section
      formData.append('about_idea_title', document.getElementById('about-idea-title')?.value || '');
      formData.append('about_idea_intro', document.getElementById('about-idea-intro')?.value || '');
      formData.append('about_idea_paragraph_1', document.getElementById('about-idea-paragraph-1')?.value || '');
      formData.append('about_idea_paragraph_2', document.getElementById('about-idea-paragraph-2')?.value || '');
      formData.append('about_idea_paragraph_3', document.getElementById('about-idea-paragraph-3')?.value || '');
      formData.append('about_idea_signature', document.getElementById('about-idea-signature')?.value || '');
      
      // How to Find Us section
      formData.append('about_location_title', document.getElementById('about-location-title')?.value || '');
      formData.append('about_location_paragraph_1', document.getElementById('about-location-paragraph-1')?.value || '');
      formData.append('about_location_paragraph_2', document.getElementById('about-location-paragraph-2')?.value || '');
      formData.append('about_location_paragraph_3', document.getElementById('about-location-paragraph-3')?.value || '');
      formData.append('about_location_paragraph_4', document.getElementById('about-location-paragraph-4')?.value || '');
      formData.append('about_location_coordinates', document.getElementById('about-location-coordinates')?.value || '');
      formData.append('about_location_deer_warning', document.getElementById('about-location-deer-warning')?.value || '');
      
      // About the Location section
      formData.append('about_attractions_title', document.getElementById('about-attractions-title')?.value || '');
      formData.append('about_attractions_lead', document.getElementById('about-attractions-lead')?.value || '');
      
      // Attractions cards
      formData.append('about_procter_title', document.getElementById('about-procter-title')?.value || '');
      formData.append('about_procter_distance', document.getElementById('about-procter-distance')?.value || '');
      formData.append('about_procter_description', document.getElementById('about-procter-description')?.value || '');
      
      formData.append('about_halcyon_title', document.getElementById('about-halcyon-title')?.value || '');
      formData.append('about_halcyon_distance', document.getElementById('about-halcyon-distance')?.value || '');
      formData.append('about_halcyon_description', document.getElementById('about-halcyon-description')?.value || '');
      
      formData.append('about_whitewater_title', document.getElementById('about-whitewater-title')?.value || '');
      formData.append('about_whitewater_distance', document.getElementById('about-whitewater-distance')?.value || '');
      formData.append('about_whitewater_description', document.getElementById('about-whitewater-description')?.value || '');
      
      formData.append('about_nelson_title', document.getElementById('about-nelson-title')?.value || '');
      formData.append('about_nelson_distance', document.getElementById('about-nelson-distance')?.value || '');
      formData.append('about_nelson_description', document.getElementById('about-nelson-description')?.value || '');
      
      // Provincial Parks section
      formData.append('about_parks_title', document.getElementById('about-parks-title')?.value || '');
      formData.append('about_parks_intro', document.getElementById('about-parks-intro')?.value || '');
      formData.append('about_parks_list', document.getElementById('about-parks-list')?.value || '');
      
      try {
        const response = await fetch('api.php', {
          method: 'POST',
          body: formData
        });
        
        if (response.ok) {
          const result = await response.json();
          if (result.success) {
            showStatus('About us page content saved successfully!');
          } else {
            showStatus('Failed to save: ' + (result.error || 'Unknown error'), 'error');
          }
        } else {
          showStatus('Failed to save about content', 'error');
        }
      } catch (error) {
        console.error('Error saving about content:', error);
        showStatus('Error saving about content: ' + error.message, 'error');
      }
    });
  }
}

// Initialize save handler for homepage rooms
function initHomepageRoomsSaveHandler() {
  const saveHomepageRoomsBtn = document.getElementById('save-homepage-rooms');
  if (saveHomepageRoomsBtn) {
    saveHomepageRoomsBtn.addEventListener('click', async () => {
      const stored = localStorage.getItem('btb_homepage_rooms') || '{}';
      const storedJson = JSON.parse(stored);
      
      const data = {
        basement: {
          imageUrl: (document.getElementById('room-basement-card-path')?.textContent) || storedJson.basement?.imageUrl || '',
          title: document.getElementById('room-basement-card-title').value,
          description: document.getElementById('room-basement-card-description').value,
          price: document.getElementById('room-basement-card-price').value
        },
        groundQueen: {
          imageUrl: (document.getElementById('room-ground-queen-card-path')?.textContent) || storedJson.groundQueen?.imageUrl || '',
          title: document.getElementById('room-ground-queen-card-title').value,
          description: document.getElementById('room-ground-queen-card-description').value,
          price: document.getElementById('room-ground-queen-card-price').value
        },
        groundTwin: {
          imageUrl: (document.getElementById('room-ground-twin-card-path')?.textContent) || storedJson.groundTwin?.imageUrl || '',
          title: document.getElementById('room-ground-twin-card-title').value,
          description: document.getElementById('room-ground-twin-card-description').value,
          price: document.getElementById('room-ground-twin-card-price').value
        },
        second: {
          imageUrl: (document.getElementById('room-second-card-path')?.textContent) || storedJson.second?.imageUrl || '',
          title: document.getElementById('room-second-card-title').value,
          description: document.getElementById('room-second-card-description').value,
          price: document.getElementById('room-second-card-price').value
        }
      };
      
      localStorage.setItem('btb_homepage_rooms', JSON.stringify(data));
      showStatus('Homepage rooms cards saved successfully!');
    });
  }
}

// Initialize save handler for wellness experiences
function initWellnessExperiencesSaveHandler() {
  const saveWellnessBtn = document.getElementById('save-wellness-experiences');
  if (saveWellnessBtn) {
    saveWellnessBtn.addEventListener('click', async () => {
      const stored = localStorage.getItem('btb_wellness_experiences') || '{}';
      const storedJson = JSON.parse(stored);
      
      const data = {
        title: document.getElementById('wellness-title').value,
        description: document.getElementById('wellness-description').value,
        massage: {
          imageUrl: (document.getElementById('wellness-massage-path')?.textContent) || storedJson.massage?.imageUrl || '',
          title: document.getElementById('wellness-massage-title').value,
          description: document.getElementById('wellness-massage-description').value
        },
        yoga: {
          imageUrl: (document.getElementById('wellness-yoga-path')?.textContent) || storedJson.yoga?.imageUrl || '',
          title: document.getElementById('wellness-yoga-title').value,
          description: document.getElementById('wellness-yoga-description').value
        },
        sauna: {
          imageUrl: (document.getElementById('wellness-sauna-path')?.textContent) || storedJson.sauna?.imageUrl || '',
          title: document.getElementById('wellness-sauna-title').value,
          description: document.getElementById('wellness-sauna-description').value
        }
      };
      
      localStorage.setItem('btb_wellness_experiences', JSON.stringify(data));
      showStatus('Wellness Experiences saved successfully!');
    });
  }
}

// Initialize admin panel
document.addEventListener('DOMContentLoaded', () => {
  // Check authentication
  checkAdminAuth();
  
  // Initialize login form
  initAdminLogin();
  
  // Initialize two-level navigation
  // Primary level tabs (Bookings Management, Content Management, Account Management)
  document.querySelectorAll('.admin-nav-tab-primary').forEach(tab => {
    tab.addEventListener('click', () => {
      const primary = tab.getAttribute('data-primary');
      switchPrimarySection(primary);
    });
  });
  
  // Secondary level tabs (subsections)
  document.querySelectorAll('.admin-nav-tab-secondary').forEach(tab => {
    tab.addEventListener('click', () => {
      const section = tab.getAttribute('data-section');
      showSection(section);
    });
  });
  
  // Initialize default primary section (Bookings Management)
  switchPrimarySection('bookings');
  
  // Initialize logout
  const logoutBtn = document.getElementById('admin-logout');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', adminLogout);
  }
  
  // Initialize save homepage button
  const saveHomepageBtn = document.getElementById('save-homepage');
  if (saveHomepageBtn) {
    saveHomepageBtn.addEventListener('click', async () => {
      // Save Home Page content
      const homepageDescription = document.getElementById('homepage-main-description').value;
      const homepageSubtitle = document.getElementById('homepage-main-subtitle').value;
      
      // Read current image paths from path labels to avoid overwriting with empty values
      const stored = localStorage.getItem('btb_content');
      const storedJson = stored ? JSON.parse(stored) : {};
      const heroPathEl = document.getElementById('hero-image-path');
      const hero2PathEl = document.getElementById('hero2-image-path');
      
      const heroImageUrl = (heroPathEl && heroPathEl.textContent) || storedJson.heroImageUrl || '';
      const hero2ImageUrl = (hero2PathEl && hero2PathEl.textContent) || storedJson.hero2ImageUrl || '';
      
      const currentContent = getStoredData('btb_content') || getDefaultContent();
      const content = {
        ...currentContent,
        homepageDescription: homepageDescription,
        homepageSubtitle: homepageSubtitle,
        heroImageUrl: heroImageUrl,
        hero2ImageUrl: hero2ImageUrl
      };
      
      try {
        const saved = await saveContentToServer(content);
        if (saved) {
          // Continue to save Floor Plan and Rooms
        }
      } catch (error) {
        console.log('Server save failed, saving to localStorage');
      }
      
      setStoredData('btb_content', content);
      
      // Save Floor Plan data
      const floorplanStored = localStorage.getItem('btb_floorplan_settings');
      const floorplanStoredJson = floorplanStored ? JSON.parse(floorplanStored) : {};
      const basementPathEl = document.getElementById('basement-image-path');
      const groundPathEl = document.getElementById('ground-image-path');
      const loftPathEl = document.getElementById('loft-image-path');

      const currentBasementImage = (basementPathEl && basementPathEl.textContent) || floorplanStoredJson.basement_image_url || '';
      const currentGroundImage = (groundPathEl && groundPathEl.textContent) || floorplanStoredJson.ground_image_url || floorplanStoredJson.ground_queen_image || '';
      const currentLoftImage = (loftPathEl && loftPathEl.textContent) || floorplanStoredJson.loft_image_url || '';

      const floorplanData = {
        basementSubtitle: document.getElementById('basement-subtitle').value,
        basementDescription: document.getElementById('basement-description').value,
        basementImageUrl: currentBasementImage,
        groundSubtitle: document.getElementById('ground-subtitle').value,
        groundDescription: document.getElementById('ground-description').value,
        groundQueenImage: currentGroundImage,
        groundTwinImage: '',
        loftSubtitle: document.getElementById('loft-subtitle').value,
        loftDescription: document.getElementById('loft-description').value,
        loftImageUrl: currentLoftImage
      };
      
      try {
        const formData = new FormData();
        formData.append('action', 'save_floorplan');
        Object.entries(floorplanData).forEach(([key, value]) => {
          formData.append(key, value || '');
        });
        
        const response = await fetch('api.php', {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
          const localStorageData = {
            basement_subtitle: floorplanData.basementSubtitle,
            basement_description: floorplanData.basementDescription,
            basement_image_url: floorplanData.basementImageUrl,
            ground_subtitle: floorplanData.groundSubtitle,
            ground_description: floorplanData.groundDescription,
            ground_image_url: floorplanData.groundQueenImage,
            ground_queen_image: floorplanData.groundQueenImage,
            ground_twin_image: floorplanData.groundTwinImage,
            loft_subtitle: floorplanData.loftSubtitle,
            loft_description: floorplanData.loftDescription,
            loft_image_url: floorplanData.loftImageUrl
          };
          localStorage.setItem('btb_floorplan_settings', JSON.stringify(localStorageData));
        }
      } catch (error) {
        console.log('Floor plan save failed:', error);
      }
      
      // Save Rooms cards data
      const roomsStored = localStorage.getItem('btb_homepage_rooms') || '{}';
      const roomsStoredJson = JSON.parse(roomsStored);
      
      const roomsData = {
        basement: {
          imageUrl: (document.getElementById('room-basement-card-path')?.textContent) || roomsStoredJson.basement?.imageUrl || '',
          title: document.getElementById('room-basement-card-title').value,
          description: document.getElementById('room-basement-card-description').value,
          price: document.getElementById('room-basement-card-price').value
        },
        groundQueen: {
          imageUrl: (document.getElementById('room-ground-queen-card-path')?.textContent) || roomsStoredJson.groundQueen?.imageUrl || '',
          title: document.getElementById('room-ground-queen-card-title').value,
          description: document.getElementById('room-ground-queen-card-description').value,
          price: document.getElementById('room-ground-queen-card-price').value
        },
        groundTwin: {
          imageUrl: (document.getElementById('room-ground-twin-card-path')?.textContent) || roomsStoredJson.groundTwin?.imageUrl || '',
          title: document.getElementById('room-ground-twin-card-title').value,
          description: document.getElementById('room-ground-twin-card-description').value,
          price: document.getElementById('room-ground-twin-card-price').value
        },
        second: {
          imageUrl: (document.getElementById('room-second-card-path')?.textContent) || roomsStoredJson.second?.imageUrl || '',
          title: document.getElementById('room-second-card-title').value,
          description: document.getElementById('room-second-card-description').value,
          price: document.getElementById('room-second-card-price').value
        }
      };
      
      localStorage.setItem('btb_homepage_rooms', JSON.stringify(roomsData));
      
      showStatus('Home Page content (including Floor Plan and Rooms) saved successfully!');
    });
  }
  
  // Initialize save floorplan button
  const saveFloorplanBtn = document.getElementById('save-floorplan');
  if (saveFloorplanBtn) {
    saveFloorplanBtn.addEventListener('click', async () => {
      console.log('Saving floorplan data...');
      // Read current image paths from localStorage or visible path labels to avoid overwriting with empty values
      const stored = localStorage.getItem('btb_floorplan_settings');
      const storedJson = stored ? JSON.parse(stored) : {};
      const basementPathEl = document.getElementById('basement-image-path');
      const groundPathEl = document.getElementById('ground-image-path');
      const loftPathEl = document.getElementById('loft-image-path');

      // Universal: use consistent field names (with fallback for compatibility)
      const currentBasementImage = (basementPathEl && basementPathEl.textContent) || storedJson.basement_image_url || '';
      const currentGroundImage = (groundPathEl && groundPathEl.textContent) || storedJson.ground_image_url || storedJson.ground_queen_image || '';
      const currentLoftImage = (loftPathEl && loftPathEl.textContent) || storedJson.loft_image_url || '';

      const floorplanData = {
        basementSubtitle: document.getElementById('basement-subtitle').value,
        basementDescription: document.getElementById('basement-description').value,
        basementImageUrl: currentBasementImage,
        groundSubtitle: document.getElementById('ground-subtitle').value,
        groundDescription: document.getElementById('ground-description').value,
        groundQueenImage: currentGroundImage,
        groundTwinImage: '',
        loftSubtitle: document.getElementById('loft-subtitle').value,
        loftDescription: document.getElementById('loft-description').value,
        loftImageUrl: currentLoftImage
      };
      
      console.log('Floorplan data to save:', floorplanData);
      
      try {
        const formData = new FormData();
        formData.append('action', 'save_floorplan');
        Object.entries(floorplanData).forEach(([key, value]) => {
          formData.append(key, value || '');
        });
        
        console.log('Sending request to API...');
        const response = await fetch('api.php', {
          method: 'POST',
          body: formData
        });
        
        console.log('Response status:', response.status);
        const result = await response.json();
        console.log('API response:', result);
        
        if (result.success) {
          // Save to localStorage for immediate update on main site
          // Convert camelCase to underscore format for consistency with API
          // Universal: use consistent field names for all sections
          const localStorageData = {
            basement_subtitle: floorplanData.basementSubtitle,
            basement_description: floorplanData.basementDescription,
            basement_image_url: floorplanData.basementImageUrl,
            ground_subtitle: floorplanData.groundSubtitle,
            ground_description: floorplanData.groundDescription,
            ground_image_url: floorplanData.groundQueenImage, // Universal field name
            ground_queen_image: floorplanData.groundQueenImage, // Keep for compatibility
            ground_twin_image: floorplanData.groundTwinImage,
            loft_subtitle: floorplanData.loftSubtitle,
            loft_description: floorplanData.loftDescription,
            loft_image_url: floorplanData.loftImageUrl
          };
          localStorage.setItem('btb_floorplan_settings', JSON.stringify(localStorageData));
          console.log('Data saved to localStorage');
          showStatus('Floor plan content saved successfully!');
          return;
        } else {
          console.log('API returned error:', result.error);
        }
      } catch (error) {
        console.log('Server save failed:', error);
        // Save to localStorage even if server fails
        // Convert camelCase to underscore format for consistency with API
        // Universal: use consistent field names for all sections
        const localStorageData = {
          basement_subtitle: floorplanData.basementSubtitle,
          basement_description: floorplanData.basementDescription,
          basement_image_url: floorplanData.basementImageUrl,
          ground_subtitle: floorplanData.groundSubtitle,
          ground_description: floorplanData.groundDescription,
          ground_image_url: floorplanData.groundQueenImage, // Universal field name
          ground_queen_image: floorplanData.groundQueenImage, // Keep for compatibility
          ground_twin_image: floorplanData.groundTwinImage,
          loft_subtitle: floorplanData.loftSubtitle,
          loft_description: floorplanData.loftDescription,
          loft_image_url: floorplanData.loftImageUrl
        };
        localStorage.setItem('btb_floorplan_settings', JSON.stringify(localStorageData));
        console.log('Data saved to localStorage as fallback');
      }
      
      showStatus('Floor plan content saved successfully!');
    });
  }
  
  // Initialize save contact button
  const saveContactBtn = document.getElementById('save-contact');
  if (saveContactBtn) {
    saveContactBtn.addEventListener('click', async () => {
      const content = getStoredData('btb_content') || getDefaultContent();
      content.contactPhone = document.getElementById('contact-phone').value;
      content.contactEmail = document.getElementById('contact-email').value;
      content.contactAddress = document.getElementById('contact-address').value;
      
      try {
        const saved = await saveContentToServer(content);
        if (saved) {
          showStatus('Contact information saved successfully!');
          return;
        }
      } catch (error) {
        console.log('Server save failed, saving to localStorage');
      }
      
      setStoredData('btb_content', content);
      showStatus('Contact information saved successfully!');
    });
  }

  // Initialize save buttons for room pages
  initRoomPageSaveHandlers();
  
  // Initialize save buttons for page content
  initPageContentSaveHandlers();

  // Initialize retreat helper UI (buttons + content status badge)
  initRetreatHelperUI();
  
  // Initialize save buttons for homepage rooms and wellness
  initHomepageRoomsSaveHandler();
  initWellnessExperiencesSaveHandler();
  
  // Initialize forms
  initAdminForms();
  
  // Initialize image upload
  initImageUpload();
  
  // Load initial data
  loadSectionData('dashboard');
});

// ==========================================
// BOOKINGS MANAGEMENT
// ==========================================

// Load bookings data
async function loadBookingsData() {
  const loadingEl = document.getElementById('bookings-loading');
  const listEl = document.getElementById('bookings-list');
  const emptyEl = document.getElementById('bookings-empty');
  
  if (loadingEl) loadingEl.style.display = 'block';
  if (listEl) listEl.style.display = 'none';
  if (emptyEl) emptyEl.style.display = 'none';
  
  try {
    // Get filters
    const status = document.getElementById('bookings-filter-status')?.value || '';
    const room = document.getElementById('bookings-filter-room')?.value || '';
    
    // Get date values from Flatpickr or native input
    const dateFromInput = document.getElementById('bookings-filter-date-from');
    const dateToInput = document.getElementById('bookings-filter-date-to');
    
    let dateFrom = '';
    let dateTo = '';
    
    if (dateFromInput) {
      if (dateFromInput._flatpickr) {
        dateFrom = dateFromInput._flatpickr.input.value || '';
      } else {
        dateFrom = dateFromInput.value || '';
      }
    }
    
    if (dateToInput) {
      if (dateToInput._flatpickr) {
        dateTo = dateToInput._flatpickr.input.value || '';
      } else {
        dateTo = dateToInput.value || '';
      }
    }
    
    // Load room bookings
    const params = new URLSearchParams({ action: 'get_bookings' });
    if (status) params.append('status', status);
    if (room && room !== 'Massage') params.append('room_name', room);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (!response.ok) {
      throw new Error('Failed to load bookings');
    }
    
    const result = await response.json();
    
    let allBookings = [];
    
    // Add room bookings
    if (result.success && result.data?.bookings) {
      allBookings = result.data.bookings.map(booking => ({
        ...booking,
        booking_type: 'room'
      }));
    }
    
    // Load massage bookings if "All Rooms" or "Massage" is selected
    if (!room || room === 'Massage') {
      try {
        console.log('Loading massage bookings...', { status, dateFrom, dateTo });
        const massageParams = new URLSearchParams({ action: 'get_massage_bookings' });
        if (status) massageParams.append('status', status);
        if (dateFrom) massageParams.append('date_from', dateFrom);
        if (dateTo) massageParams.append('date_to', dateTo);
        
        console.log('Massage bookings request URL:', 'api.php?' + massageParams.toString());
        
        const massageResponse = await fetch('api.php?' + massageParams.toString(), {
          method: 'GET'
        });
        
        console.log('Massage bookings response status:', massageResponse.status, massageResponse.ok);
        
        if (massageResponse.ok) {
          const massageContentType = massageResponse.headers.get('content-type');
          console.log('Massage bookings content-type:', massageContentType);
          
          if (massageContentType && massageContentType.includes('application/json')) {
            try {
              const massageResult = await massageResponse.json();
              console.log('Massage bookings result:', massageResult);
              
              if (massageResult.success && massageResult.data?.bookings) {
                // API уже отфильтровал по статусу и дате, используем все полученные бронирования
                let massageBookings = massageResult.data.bookings;
                console.log('Massage bookings count:', massageBookings.length);
                
                // Convert massage bookings to unified format
                const convertedMassageBookings = massageBookings.map(booking => ({
                  id: booking.id,
                  booking_type: 'massage',
                  room_name: 'Massage',
                  massage_date: booking.massage_date,
                  massage_time: booking.massage_time,
                  massage_type: booking.massage_type,
                  duration: booking.duration,
                  guest_name: booking.guest_name,
                  email: booking.email,
                  phone: booking.phone,
                  status: booking.status,
                  created_at: booking.created_at,
                  confirmation_code: booking.confirmation_code || `MASS-${booking.id}`
                }));
                
                console.log('Converted massage bookings:', convertedMassageBookings);
                allBookings = [...allBookings, ...convertedMassageBookings];
              } else {
                console.warn('Massage bookings result:', massageResult);
              }
            } catch (jsonError) {
              console.error('Failed to parse massage bookings JSON:', jsonError);
            }
          } else {
            const text = await massageResponse.text();
            console.error('API returned non-JSON response for massage bookings:', text.substring(0, 200));
          }
        } else {
          const errorText = await massageResponse.text();
          console.warn('Failed to load massage bookings: HTTP', massageResponse.status, errorText.substring(0, 200));
        }
      } catch (massageError) {
        console.error('Failed to load massage bookings:', massageError);
        // Continue without massage bookings
      }
    }
    
    // Sort by created_at (newest first)
    allBookings.sort((a, b) => {
      const dateA = new Date(a.created_at || 0);
      const dateB = new Date(b.created_at || 0);
      return dateB - dateA;
    });
    
    if (loadingEl) loadingEl.style.display = 'none';
    
    if (allBookings.length > 0) {
      if (listEl) {
        listEl.style.display = 'block';
        renderBookingsList(allBookings);
      }
    } else {
      if (emptyEl) emptyEl.style.display = 'block';
    }
  } catch (error) {
    console.error('Load bookings error:', error);
    if (loadingEl) loadingEl.style.display = 'none';
    if (emptyEl) emptyEl.style.display = 'block';
    showStatus('Failed to load bookings: ' + error.message, 'error');
  }
}

// Render bookings list
function renderBookingsList(bookings) {
  const listEl = document.getElementById('bookings-list');
  if (!listEl) return;
  
  listEl.innerHTML = '';
  
  bookings.forEach(booking => {
    const card = document.createElement('div');
    card.className = 'booking-card';
    
    // Determine status display based on booking status and payment status
    let statusText = '';
    let statusClass = booking.status || 'pending';
    
    if (booking.booking_type === 'massage') {
      // Massage booking status logic
      if (booking.status === 'pending') {
        statusText = 'Awaiting Confirmation';
        statusClass = 'pending';
      } else if (booking.status === 'cancelled') {
        statusText = 'Rejected';
        statusClass = 'cancelled';
      } else if (booking.status === 'confirmed') {
        // Check if massage date has passed
        const massageDate = new Date(booking.massage_date + 'T00:00:00');
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (massageDate < today) {
          statusText = 'Completed';
          statusClass = 'completed';
        } else {
          statusText = 'Confirmed';
          statusClass = 'confirmed';
        }
      } else if (booking.status === 'completed') {
        statusText = 'Completed';
        statusClass = 'completed';
      } else {
        statusText = booking.status || 'Pending';
      }
    } else {
      // Room booking status logic
      if (booking.status === 'pending') {
        statusText = 'Awaiting Confirmation';
        statusClass = 'pending';
      } else if (booking.status === 'cancelled') {
        statusText = 'Rejected';
        statusClass = 'cancelled';
      } else if (booking.status === 'confirmed') {
        if (booking.payment_status === 'paid') {
          // Check if check-in date has passed
          const checkinDate = new Date(booking.checkin_date + 'T00:00:00');
          const today = new Date();
          today.setHours(0, 0, 0, 0);
          
          if (checkinDate > today) {
            statusText = 'Awaiting Check-in';
            statusClass = 'checked_in';
          } else {
            // Check if checkout date has passed
            const checkoutDate = new Date(booking.checkout_date + 'T00:00:00');
            if (checkoutDate < today) {
              statusText = 'Completed';
              statusClass = 'completed';
            } else {
              statusText = 'Awaiting Check-in';
              statusClass = 'checked_in';
            }
          }
        } else {
          statusText = 'Awaiting Payment';
          statusClass = 'confirmed';
        }
      } else {
        statusText = booking.status || 'Pending';
      }
    }
    
    const paymentStatusClass = booking.payment_status || 'pending';
    
    card.innerHTML = `
      <div class="booking-card-header">
        <div>
          <h3 style="margin: 0 0 8px 0;">Booking #${booking.id || '—'}</h3>
          <div style="display: flex; align-items: center; gap: 8px;">
            <span class="booking-status-badge ${statusClass}">${statusText}</span>
            ${booking.payment_status === 'paid' ? `<span class="payment-status-badge paid">Paid</span>` : ''}
          </div>
        </div>
        <div style="text-align: right;">
          <div style="font-size: 12px; color: #718096; margin-bottom: 4px;">Confirmation Code</div>
          <div style="font-family: monospace; font-weight: 600;">${booking.confirmation_code || '—'}</div>
        </div>
      </div>
      
      <div class="booking-details-grid">
        ${booking.booking_type === 'massage' ? `
          <div class="booking-detail-item">
            <div class="booking-detail-label">Service</div>
            <div class="booking-detail-value">${escapeHtml(booking.room_name || 'Massage')}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Date</div>
            <div class="booking-detail-value">${formatDate(booking.massage_date)}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Time</div>
            <div class="booking-detail-value">${booking.massage_time || '—'}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Type</div>
            <div class="booking-detail-value">${escapeHtml(booking.massage_type || '—')}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Duration</div>
            <div class="booking-detail-value">${booking.duration ? `${booking.duration} min` : '—'}</div>
          </div>
        ` : `
          <div class="booking-detail-item">
            <div class="booking-detail-label">Room</div>
            <div class="booking-detail-value">${escapeHtml(booking.room_name || '—')}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Check-in</div>
            <div class="booking-detail-value">${formatDate(booking.checkin_date)}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Check-out</div>
            <div class="booking-detail-value">${formatDate(booking.checkout_date)}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Guests</div>
            <div class="booking-detail-value">${booking.guests_count || '—'}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Pets</div>
            <div class="booking-detail-value">${booking.pets ? 'Yes' : 'No'}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Total Amount</div>
            <div class="booking-detail-value">${booking.currency || 'CAD'} ${parseFloat(booking.total_amount || 0).toFixed(2)}</div>
          </div>
        `}
        <div class="booking-detail-item">
          <div class="booking-detail-label">Guest Name</div>
          <div class="booking-detail-value">${escapeHtml(booking.guest_name || '—')}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Email</div>
          <div class="booking-detail-value">${escapeHtml(booking.email || '—')}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Phone</div>
          <div class="booking-detail-value">${escapeHtml(booking.phone || '—')}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Created</div>
          <div class="booking-detail-value">${formatDateTime(booking.created_at)}</div>
        </div>
      </div>
      
      <div class="booking-actions">
        ${booking.booking_type === 'massage' ? `
          ${booking.status === 'pending' ? `
            <button class="admin-btn admin-btn-primary" onclick="window.confirmMassageBooking(${booking.id})">Confirm</button>
            <button class="admin-btn admin-btn-danger" onclick="window.cancelMassageBooking(${booking.id})">Reject</button>
            <button class="admin-btn admin-btn-danger" onclick="window.deleteMassageBooking(${booking.id})">Delete</button>
          ` : ''}
          ${booking.status === 'confirmed' ? `
            <button class="admin-btn admin-btn-danger" onclick="window.cancelMassageBooking(${booking.id})">Cancel</button>
          ` : ''}
        ` : `
          ${booking.status === 'pending' ? `
            <button class="admin-btn admin-btn-primary" onclick="window.confirmBooking(${booking.id})">Confirm</button>
            <button class="admin-btn admin-btn-danger" onclick="window.cancelBooking(${booking.id})">Reject</button>
            <button class="admin-btn admin-btn-danger" onclick="window.deleteBooking(${booking.id})">Delete</button>
          ` : ''}
          ${booking.status === 'confirmed' && booking.payment_status !== 'paid' ? `
            <button class="admin-btn admin-btn-danger" onclick="window.cancelBooking(${booking.id})">Cancel</button>
          ` : ''}
          ${booking.status === 'confirmed' ? `
            <button class="admin-btn admin-btn-secondary" onclick="window.viewBookingDetails(${booking.id})">View Details</button>
          ` : ''}
        `}
      </div>
    `;
    
    listEl.appendChild(card);
  });
}

// Confirm booking
async function confirmBooking(bookingId) {
  if (!confirm('Are you sure you want to confirm this booking?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'confirm_booking');
    formData.append('booking_id', bookingId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to confirm booking');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Booking confirmed successfully!');
      loadBookingsData();
      updateBookingsDashboardStats();
    } else {
      throw new Error(result.error || 'Failed to confirm booking');
    }
  } catch (error) {
    console.error('Confirm booking error:', error);
    showStatus('Failed to confirm booking: ' + error.message, 'error');
  }
}

// Cancel booking
async function cancelBooking(bookingId) {
  const reason = prompt('Please enter a reason for cancellation (optional):');
  if (reason === null) {
    return; // User cancelled
  }
  
  if (!confirm('Are you sure you want to cancel this booking?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'cancel_booking');
    formData.append('booking_id', bookingId);
    if (reason) {
      formData.append('reason', reason);
    }
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to cancel booking');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Booking cancelled successfully!');
      loadBookingsData();
      updateBookingsDashboardStats();
    } else {
      throw new Error(result.error || 'Failed to cancel booking');
    }
  } catch (error) {
    console.error('Cancel booking error:', error);
    showStatus('Failed to cancel booking: ' + error.message, 'error');
  }
}

// Delete booking (only for pending bookings)
async function deleteBooking(bookingId) {
  console.log('deleteBooking called with bookingId:', bookingId);
  
  if (!bookingId || bookingId <= 0) {
    console.error('Invalid booking ID:', bookingId);
    showStatus('Invalid booking ID', 'error');
    return;
  }
  
  // Используем кастомное модальное окно для подтверждения
  return new Promise((resolve) => {
    // Создаем модальное окно
    const modal = document.createElement('div');
    modal.className = 'delete-confirm-modal active';
    modal.innerHTML = `
      <div class="delete-confirm-content">
        <h3>⚠️ Delete Booking #${bookingId}</h3>
        <p>
          <strong>Are you ABSOLUTELY SURE you want to PERMANENTLY DELETE this booking?</strong>
        </p>
        <p style="color: #718096;">
          This will:
          <ul style="margin: 12px 0; padding-left: 20px; color: #718096;">
            <li>Delete the booking from the database</li>
            <li>Remove it from the admin panel</li>
            <li>Remove it from the user account</li>
          </ul>
        </p>
        <p style="color: #e53e3e; font-weight: 600;">
          This action CANNOT be undone!
        </p>
        <div class="delete-confirm-actions">
          <button class="delete-confirm-btn delete-confirm-btn-cancel">Cancel</button>
          <button class="delete-confirm-btn delete-confirm-btn-delete">Delete Permanently</button>
        </div>
      </div>
    `;
    
    document.body.appendChild(modal);
    
    // Обработчики кнопок
    const cancelBtn = modal.querySelector('.delete-confirm-btn-cancel');
    const deleteBtn = modal.querySelector('.delete-confirm-btn-delete');
    
    const closeModal = () => {
      modal.classList.remove('active');
      setTimeout(() => {
        document.body.removeChild(modal);
      }, 300);
    };
    
    cancelBtn.addEventListener('click', () => {
      console.log('User cancelled deletion');
      closeModal();
      resolve(false);
    });
    
    deleteBtn.addEventListener('click', async () => {
      console.log('User confirmed deletion, proceeding...');
      closeModal();
      resolve(true);
      
      // Выполняем удаление
      await performDeleteBooking(bookingId);
    });
    
    // Закрытие по клику вне модального окна
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        console.log('User cancelled deletion (clicked outside)');
        closeModal();
        resolve(false);
      }
    });
  });
}

// Функция для выполнения удаления
async function performDeleteBooking(bookingId) {
  try {
    console.log('Starting deletion process for booking:', bookingId);
    
    const formData = new FormData();
    formData.append('action', 'delete_booking');
    formData.append('booking_id', bookingId);
    
    console.log('Sending delete request to api.php');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    console.log('Response received:', response.status, response.statusText);
    
    if (!response.ok) {
      const errorText = await response.text();
      console.error('Delete booking response error:', response.status, errorText);
      throw new Error(`HTTP ${response.status}: Failed to delete booking`);
    }
    
    const result = await response.json();
    console.log('Delete booking result:', result);
    
    if (result.success) {
      console.log('Booking deleted successfully');
      showStatus('Booking deleted successfully!');
      
      // Reload bookings list
      loadBookingsData();
      updateBookingsDashboardStats();
      
      // Note: Booking is deleted from database
      // If user has it in localStorage, it will be removed when they refresh their account page
      // The booking will no longer appear in their account because it's deleted from the database
    } else {
      console.error('Delete booking failed:', result.error);
      throw new Error(result.error || 'Failed to delete booking');
    }
  } catch (error) {
    console.error('Delete booking error:', error);
    showStatus('Failed to delete booking: ' + error.message, 'error');
  }
}

// View booking details
async function viewBookingDetails(bookingId) {
  try {
    const formData = new FormData();
    formData.append('action', 'get_booking');
    formData.append('booking_id', bookingId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to load booking details');
    }
    
    const result = await response.json();
    
    if (result.success && result.data?.booking) {
      const booking = result.data.booking;
      alert(`Booking Details:\n\n` +
        `ID: ${booking.id}\n` +
        `Room: ${booking.room_name}\n` +
        `Check-in: ${formatDate(booking.checkin_date)}\n` +
        `Check-out: ${formatDate(booking.checkout_date)}\n` +
        `Guests: ${booking.guests_count}\n` +
        `Pets: ${booking.pets ? 'Yes' : 'No'}\n` +
        `Guest: ${booking.guest_name}\n` +
        `Email: ${booking.email}\n` +
        `Phone: ${booking.phone}\n` +
        `Total: ${booking.currency || 'CAD'} ${parseFloat(booking.total_amount || 0).toFixed(2)}\n` +
        `Status: ${booking.status}\n` +
        `Payment: ${booking.payment_status || 'pending'}\n` +
        `Confirmation Code: ${booking.confirmation_code || '—'}\n` +
        `Created: ${formatDateTime(booking.created_at)}\n`
      );
    }
  } catch (error) {
    console.error('View booking details error:', error);
    showStatus('Failed to load booking details: ' + error.message, 'error');
  }
}

// Initialize bookings filters
function initBookingsFilters() {
  const applyBtn = document.getElementById('bookings-filter-apply');
  const resetBtn = document.getElementById('bookings-filter-reset');
  const refreshBtn = document.getElementById('bookings-refresh');
  const dateFromInput = document.getElementById('bookings-filter-date-from');
  const dateToInput = document.getElementById('bookings-filter-date-to');
  
  // Initialize Flatpickr for date filters
  if (typeof flatpickr !== 'undefined') {
    if (dateFromInput && !dateFromInput._flatpickr) {
      // Calculate date range: current year ± 3 years
      const currentYear = new Date().getFullYear();
      const minYear = currentYear - 3;
      const maxYear = currentYear + 3;
      const minDate = new Date(minYear, 0, 1); // January 1st of minYear
      const maxDate = new Date(maxYear, 11, 31); // December 31st of maxYear
      
      flatpickr(dateFromInput, {
        dateFormat: 'Y-m-d',
        allowInput: true,
        clickOpens: true,
        minDate: minDate, // Minimum date (3 years ago)
        maxDate: maxDate, // Maximum date (3 years ahead)
        monthSelectorType: 'static', // Выпадающий список для месяцев
        yearSelectorType: 'static' // Выпадающий список для годов
      });
    }
    
    if (dateToInput && !dateToInput._flatpickr) {
      // Calculate date range: current year ± 3 years
      const currentYear = new Date().getFullYear();
      const minYear = currentYear - 3;
      const maxYear = currentYear + 3;
      const minDate = new Date(minYear, 0, 1); // January 1st of minYear
      const maxDate = new Date(maxYear, 11, 31); // December 31st of maxYear
      
      flatpickr(dateToInput, {
        dateFormat: 'Y-m-d',
        allowInput: true,
        clickOpens: true,
        minDate: minDate, // Minimum date (3 years ago)
        maxDate: maxDate, // Maximum date (3 years ahead)
        monthSelectorType: 'static', // Выпадающий список для месяцев
        yearSelectorType: 'static' // Выпадающий список для годов
      });
    }
  }
  
  if (applyBtn) {
    applyBtn.addEventListener('click', () => {
      loadBookingsData();
    });
  }
  
  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      document.getElementById('bookings-filter-status').value = '';
      document.getElementById('bookings-filter-room').value = '';
      
      // Reset Flatpickr dates
      if (dateFromInput) {
        if (dateFromInput._flatpickr) {
          dateFromInput._flatpickr.clear();
        } else {
          dateFromInput.value = '';
        }
      }
      
      if (dateToInput) {
        if (dateToInput._flatpickr) {
          dateToInput._flatpickr.clear();
        } else {
          dateToInput.value = '';
        }
      }
      
      loadBookingsData();
    });
  }
  
  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      loadBookingsData();
    });
  }
}

// ==========================================
// CALENDAR MANAGEMENT
// ==========================================

// Load calendar data
async function loadCalendarData() {
  const loadingEl = document.getElementById('admin-calendar-loading');
  const gridEl = document.getElementById('admin-calendar-grid');
  
  if (loadingEl) loadingEl.style.display = 'block';
  if (gridEl) gridEl.style.display = 'none';
  
  try {
    // Получаем выбранное бронирование из активной вкладки
    const activeTab = document.querySelector('.calendar-room-tab.active');
    const selectedRoom = activeTab ? activeTab.getAttribute('data-room') : '';
    
    // Если выбрана вкладка "Massage", загружаем только бронирования массажа
    const isMassageTab = selectedRoom === 'Massage';
    
    // Get bookings for selected booking - получаем confirmed и paid бронирования
    // Получаем все бронирования, затем фильтруем на клиенте
    let bookings = [];
    if (!isMassageTab) {
      // Загружаем бронирования комнат только если не выбрана вкладка Massage
      const params = new URLSearchParams({ action: 'get_bookings' });
      if (selectedRoom) {
        params.append('room_name', selectedRoom);
      }
      
      const bookingsResponse = await fetch('api.php?' + params.toString(), {
        method: 'GET'
      });
      
      if (bookingsResponse.ok) {
      // Проверяем, что ответ действительно JSON, а не HTML (ошибка PHP)
      const bookingsContentType = bookingsResponse.headers.get('content-type');
      if (bookingsContentType && bookingsContentType.includes('application/json')) {
        try {
          const bookingsResult = await bookingsResponse.json();
          console.log('Bookings result:', bookingsResult);
          
          if (bookingsResult.success && bookingsResult.data?.bookings) {
            const allBookings = bookingsResult.data.bookings;
            console.log('All bookings before filter:', allBookings.length, allBookings);
            console.log('Booking statuses:', allBookings.map(b => ({ id: b.id, status: b.status, payment_status: b.payment_status })));
            
            // Фильтруем бронирования для календаря - показываем все кроме cancelled
            // (pending, confirmed, completed - все показываем в календаре)
            bookings = allBookings.filter(booking => {
              const isNotCancelled = booking.status !== 'cancelled';
              console.log(`Booking ${booking.id}: status=${booking.status}, isNotCancelled=${isNotCancelled}`);
              return isNotCancelled;
            });
            console.log('Filtered bookings for calendar:', bookings.length, bookings);
          } else {
            console.warn('No bookings in result:', bookingsResult);
          }
        } catch (jsonError) {
          console.error('Failed to parse bookings JSON:', jsonError);
          // Продолжаем без бронирований
        }
      } else {
        const text = await bookingsResponse.text();
        console.error('API returned non-JSON response for bookings:', text.substring(0, 200));
        // Продолжаем без бронирований
      }
    }
    }
    
    // Get blocked dates (manual blocking)
    const blockedParams = new URLSearchParams({ action: 'get_blocked_dates' });
    if (selectedRoom && !isMassageTab) {
      blockedParams.append('room_name', selectedRoom);
    }
    
    const blockedResponse = await fetch('api.php?' + blockedParams.toString(), {
      method: 'GET'
    });
    
    // Get Airbnb sync status (for Airbnb blocked dates) - только для комнат, не для массажа
    const airbnbParams = new URLSearchParams({ action: 'get_airbnb_sync_status' });
    if (selectedRoom && !isMassageTab) {
      airbnbParams.append('room_name', selectedRoom);
    }
    
    const airbnbResponse = await fetch('api.php?' + airbnbParams.toString(), {
      method: 'GET'
    });
    
    let blockedDates = [];
    if (blockedResponse.ok) {
      // Проверяем, что ответ действительно JSON, а не HTML (ошибка PHP)
      const blockedContentType = blockedResponse.headers.get('content-type');
      if (blockedContentType && blockedContentType.includes('application/json')) {
        try {
          const blockedResult = await blockedResponse.json();
          if (blockedResult.success) {
        // Получаем ручные блокировки (периоды)
        if (blockedResult.data?.blocked_dates) {
          // Преобразуем периоды в список дат для календаря
          blockedResult.data.blocked_dates.forEach(blocked => {
            // Учитываем блокировки для конкретной комнаты и блокировки "__all__" (для всех)
            // Если выбрана конкретная комната, показываем блокировки этой комнаты и "__all__"
            // Если выбрана вкладка "All Bookings", показываем все блокировки
            const isRelevant = !selectedRoom || blocked.room_name === selectedRoom || blocked.room_name === '__all__';
            
            if (isRelevant) {
              const dateFrom = blocked.date_from || blocked.blocked_date || '';
              const dateTo = blocked.date_to || blocked.blocked_date || '';
              
              if (dateFrom && dateTo) {
                // Генерируем все даты в периоде
                const fromDate = new Date(dateFrom);
                const toDate = new Date(dateTo);
                
                for (let d = new Date(fromDate); d <= toDate; d.setDate(d.getDate() + 1)) {
                  const dateStr = d.toISOString().split('T')[0];
                  blockedDates.push(dateStr);
                }
              } else if (blocked.blocked_date) {
                // Обратная совместимость: если есть только blocked_date
                blockedDates.push(blocked.blocked_date);
              }
            }
          });
        }
        
        // Также получаем Airbnb заблокированные даты
        if (blockedResult.data?.airbnb_blocked_dates) {
          blockedDates = [...blockedDates, ...blockedResult.data.airbnb_blocked_dates];
        }
          }
        } catch (jsonError) {
          console.error('Failed to parse blocked dates JSON:', jsonError);
          // Продолжаем без заблокированных дат
        }
      } else {
        const text = await blockedResponse.text();
        console.error('API returned non-JSON response for blocked dates:', text.substring(0, 200));
        // Продолжаем без заблокированных дат
      }
    }
    
    // Убираем дубликаты
    blockedDates = [...new Set(blockedDates)];
    
    // Get massage bookings - загружаем всегда для отображения в календаре
    // Если выбрана вкладка "All Bookings" или "Massage", показываем все бронирования массажа
    // Если выбрана конкретная комната, не показываем бронирования массажа
    let massageBookings = [];
    if (!selectedRoom || isMassageTab) {
      try {
        console.log('Loading massage bookings for calendar...', { selectedRoom, isMassageTab });
        const massageParams = new URLSearchParams({ action: 'get_massage_bookings' });
        const massageResponse = await fetch('api.php?' + massageParams.toString(), {
          method: 'GET'
        });
        
        console.log('Massage bookings response status:', massageResponse.status);
        
        if (massageResponse.ok) {
          const massageContentType = massageResponse.headers.get('content-type');
          console.log('Massage bookings content-type:', massageContentType);
          
          if (massageContentType && massageContentType.includes('application/json')) {
            try {
              const massageResult = await massageResponse.json();
              console.log('Massage bookings result:', massageResult);
              
              if (massageResult.success && massageResult.data?.bookings) {
                const allMassageBookings = massageResult.data.bookings;
                console.log('All massage bookings before filter:', allMassageBookings.length, allMassageBookings);
                console.log('Massage booking statuses:', allMassageBookings.map(b => ({ id: b.id, status: b.status })));
                
                // Фильтруем бронирования массажа для календаря - показываем все кроме cancelled
                // (pending, confirmed, completed - все показываем в календаре)
                massageBookings = allMassageBookings.filter(booking => {
                  const isNotCancelled = booking.status !== 'cancelled';
                  console.log(`Massage booking ${booking.id}: status=${booking.status}, isNotCancelled=${isNotCancelled}`);
                  return isNotCancelled;
                });
                console.log('Filtered massage bookings for calendar:', massageBookings.length, massageBookings);
              }
            } catch (jsonError) {
              console.error('Failed to parse massage bookings JSON:', jsonError);
            }
          } else {
            const text = await massageResponse.text();
            console.error('API returned non-JSON response for massage bookings:', text.substring(0, 200));
          }
        } else {
          const errorText = await massageResponse.text();
          console.warn('Failed to fetch massage bookings:', massageResponse.status, errorText.substring(0, 200));
        }
      } catch (massageError) {
        console.error('Failed to fetch massage bookings:', massageError);
      }
    }
    
    if (loadingEl) loadingEl.style.display = 'none';
    if (gridEl) {
      gridEl.style.display = 'grid';
      renderAdminCalendar(gridEl, bookings, blockedDates, massageBookings);
    }
  } catch (error) {
    console.error('Load calendar error:', error);
    if (loadingEl) loadingEl.style.display = 'none';
    showStatus('Failed to load calendar: ' + error.message, 'error');
    // Показываем пустой календарь вместо полного сбоя
    if (gridEl) {
      gridEl.style.display = 'grid';
      // Рендерим календарь с пустыми данными, чтобы интерфейс был виден
      renderAdminCalendar(gridEl, [], [], []);
    }
  }
}

// Render admin calendar
let calendarStartMonth = 0; // Смещение для навигации по месяцам

function renderAdminCalendar(container, bookings, blockedDates, massageBookings = []) {
  console.log('renderAdminCalendar called with:', {
    bookingsCount: bookings.length,
    blockedDatesCount: blockedDates.length,
    massageBookingsCount: massageBookings.length,
    sampleBooking: bookings[0],
    sampleMassageBooking: massageBookings[0],
    sampleBlockedDate: blockedDates[0]
  });
  
  container.innerHTML = '';
  container.className = 'admin-calendar-grid';
  
  const today = new Date();
  const months = [];
  // Показываем 3 месяца, начиная с текущего + смещение
  for (let i = 0; i < 3; i++) {
    const date = new Date(today.getFullYear(), today.getMonth() + calendarStartMonth + i, 1);
    months.push(date);
  }
  
  const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  
  months.forEach((monthDate, monthIndex) => {
    const monthYear = monthDate.toLocaleString('en-US', { month: 'long', year: 'numeric' });
    const daysInMonth = new Date(monthDate.getFullYear(), monthDate.getMonth() + 1, 0).getDate();
    const firstDay = new Date(monthDate.getFullYear(), monthDate.getMonth(), 1).getDay();
    
    // Month header (visual separation between months)
    if (monthIndex > 0) {
      // Add empty row for spacing before month header (except for first month)
      for (let i = 0; i < 7; i++) {
        const spacer = document.createElement('div');
        spacer.className = 'admin-calendar-spacer';
        container.appendChild(spacer);
      }
    }
    
    const monthHeader = document.createElement('div');
    monthHeader.className = 'admin-calendar-month-header';
    monthHeader.textContent = monthYear;
    container.appendChild(monthHeader);
    
    // Day headers (only for first month, or for each month if you want them repeated)
    if (monthIndex === 0) {
      // Day headers only once at the top
      weekdays.forEach(day => {
        const dayHeader = document.createElement('div');
        dayHeader.className = 'admin-calendar-day-header';
        dayHeader.textContent = day;
        container.appendChild(dayHeader);
      });
    }
    
    // Empty cells for days before first day of month (keep them visible but empty for grid continuity)
    for (let i = 0; i < firstDay; i++) {
      const emptyCell = document.createElement('div');
      emptyCell.className = 'admin-calendar-day';
      emptyCell.style.visibility = 'hidden';
      emptyCell.style.pointerEvents = 'none';
      container.appendChild(emptyCell);
    }
    
    // Days
    for (let day = 1; day <= daysInMonth; day++) {
      const date = new Date(monthDate.getFullYear(), monthDate.getMonth(), day);
      const dateString = formatDateString(date);
      
      const dayCell = document.createElement('div');
      dayCell.className = 'admin-calendar-day';
      dayCell.textContent = day;
      
      const todayDate = new Date();
      todayDate.setHours(0, 0, 0, 0);
      const cellDate = new Date(date);
      cellDate.setHours(0, 0, 0, 0);
      
      // Проверяем тип бронирования для этой даты
      const bookingInfo = getBookingInfoForDate(dateString, bookings);
      const massageInfo = getMassageBookingInfoForDate(dateString, massageBookings);
      const isPast = cellDate < todayDate;
      
      // Логирование для первых нескольких дней для диагностики
      if (day <= 3 && monthIndex === 0) {
        console.log(`Date ${dateString}:`, {
          bookingInfo: bookingInfo ? { id: bookingInfo.id, checkin: bookingInfo.checkin_date, checkout: bookingInfo.checkout_date } : null,
          massageInfo: massageInfo ? { id: massageInfo.id, date: massageInfo.massage_date } : null,
          isBlocked: blockedDates.includes(dateString)
        });
      }
      
      if (blockedDates.includes(dateString)) {
        dayCell.classList.add('blocked');
        if (isPast) {
          dayCell.classList.add('past');
        }
      } else if (massageInfo) {
        // Есть бронирование массажа - розовый цвет
        dayCell.classList.add('massage');
        // Если pending, добавляем класс pending для визуального отличия
        if (massageInfo.status === 'pending') {
          dayCell.classList.add('pending');
        }
        if (isPast) {
          dayCell.classList.add('past');
        }
        dayCell.classList.add('has-booking');
      } else if (bookingInfo) {
        // Есть бронирование комнаты - определяем тип
        if (bookingInfo.payment_status === 'paid') {
          dayCell.classList.add('paid');
          // Для прошедших дат с оплаченными бронированиями - более тусклый зеленый
          if (isPast) {
            dayCell.classList.add('past');
          }
        } else if (bookingInfo.status === 'confirmed') {
          dayCell.classList.add('confirmed');
          // Для прошедших дат с одобренными бронированиями - более тусклый желтый
          if (isPast) {
            dayCell.classList.add('past');
          }
        } else if (bookingInfo.status === 'pending') {
          // Pending бронирования - оранжевый цвет
          dayCell.classList.add('pending');
          if (isPast) {
            dayCell.classList.add('past');
          }
        } else {
          dayCell.classList.add('booked');
          if (isPast) {
            dayCell.classList.add('past');
          }
        }
        dayCell.classList.add('has-booking');
      } else {
        // Нет бронирования - доступная дата
        dayCell.classList.add('available');
        if (isPast) {
          dayCell.classList.add('past');
        }
      }
      
      container.appendChild(dayCell);
    }
  });
}

// Flash field red twice (for validation)
function flashFieldTwice(field) {
  if (!field) return;
  
  // Helper function to flash once
  const flashOnce = (el) => {
    if (!el) return;
    el.classList.remove('flash-invalid');
    void el.offsetWidth; // restart animation
    el.classList.add('flash-invalid');
    setTimeout(() => {
      try {
        el.classList.remove('flash-invalid');
      } catch (_) {}
    }, 350);
  };
  
  // Flash first time
  flashOnce(field);
  
  // Flash second time after first animation completes
  setTimeout(() => {
    flashOnce(field);
  }, 700);
}

// Block date
async function blockDate() {
  const roomSelect = document.getElementById('block-room-select');
  const dateFromInput = document.getElementById('block-date-from');
  const dateToInput = document.getElementById('block-date-to');
  const reasonInput = document.getElementById('block-reason');
  
  if (!roomSelect || !dateFromInput || !dateToInput) {
    showStatus('Room and date inputs not found', 'error');
    return;
  }
  
  const roomName = roomSelect.value;
  const dateFrom = dateFromInput.value;
  const dateTo = dateToInput.value;
  const reason = reasonInput ? reasonInput.value : '';
  
  // Validate required fields and flash them red twice if empty
  let hasErrors = false;
  
  if (!roomName) {
    flashFieldTwice(roomSelect);
    hasErrors = true;
  }
  
  if (!dateFrom) {
    // Check if Flatpickr is used for date input
    let visibleDateFrom = dateFromInput;
    if (typeof flatpickr !== 'undefined' && dateFromInput._flatpickr) {
      const fpInstance = dateFromInput._flatpickr;
      if (fpInstance.altInput) {
        visibleDateFrom = fpInstance.altInput;
      }
    }
    flashFieldTwice(visibleDateFrom);
    flashFieldTwice(dateFromInput);
    hasErrors = true;
  }
  
  if (!dateTo) {
    // Check if Flatpickr is used for date input
    let visibleDateTo = dateToInput;
    if (typeof flatpickr !== 'undefined' && dateToInput._flatpickr) {
      const fpInstance = dateToInput._flatpickr;
      if (fpInstance.altInput) {
        visibleDateTo = fpInstance.altInput;
      }
    }
    flashFieldTwice(visibleDateTo);
    flashFieldTwice(dateToInput);
    hasErrors = true;
  }
  
  if (hasErrors) {
    showStatus('Please fill in all required fields', 'error');
    return;
  }
  
  if (new Date(dateFrom) > new Date(dateTo)) {
    showStatus('Date From must be before Date To', 'error');
    return;
  }
  
  try {
    // If "For all bookings" is selected, create a single record with room_name = "__all__"
    if (roomName === '__all__') {
      const formData = new FormData();
      formData.append('action', 'block_date');
      formData.append('room_name', '__all__');
      formData.append('date_from', dateFrom);
      formData.append('date_to', dateTo);
      if (reason) {
        formData.append('reason', reason);
      }
      
      const response = await fetch('api.php', {
        method: 'POST',
        body: formData
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: Failed to block date period`);
      }
      
      const result = await response.json();
      
      if (result.success) {
        showStatus('Date period blocked successfully for all bookings!');
        // Ensure we stay on Calendar Management section
        switchPrimarySection('bookings');
        showSection('calendar');
        // Reload calendar and blocked dates list
        loadCalendarData();
        loadBlockedDates();
        
        // Clear form
        roomSelect.value = '';
        dateFromInput.value = '';
        dateToInput.value = '';
        if (reasonInput) {
          reasonInput.value = '';
        }
      } else {
        showStatus(result.error || 'Failed to block date period for all bookings', 'error');
      }
    } else {
      // Block for single booking
      const formData = new FormData();
      formData.append('action', 'block_date');
      formData.append('room_name', roomName);
      formData.append('date_from', dateFrom);
      formData.append('date_to', dateTo);
      if (reason) {
        formData.append('reason', reason);
      }
      
      const response = await fetch('api.php', {
        method: 'POST',
        body: formData
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: Failed to block date period`);
      }
      
      const result = await response.json();
      
      if (result.success) {
        showStatus('Date period blocked successfully!');
        // Ensure we stay on Calendar Management section
        switchPrimarySection('bookings');
        showSection('calendar');
        // Reload calendar and blocked dates list
        loadCalendarData();
        loadBlockedDates();
        
        // Clear form
        roomSelect.value = '';
        dateFromInput.value = '';
        dateToInput.value = '';
        if (reasonInput) {
          reasonInput.value = '';
        }
      } else {
        showStatus(result.error || 'Failed to block date period', 'error');
      }
    }
  } catch (error) {
    console.error('Block date error:', error);
    showStatus('Failed to block date period: ' + error.message, 'error');
  }
}

// Unblock date period
async function unblockDate(blockedDateId) {
  if (!confirm('Are you sure you want to unblock this date period?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'unblock_date');
    formData.append('block_id', blockedDateId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to unblock date');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Date unblocked successfully!');
      loadBlockedDates();
      loadCalendarData();
    } else {
      throw new Error(result.error || 'Failed to unblock date');
    }
  } catch (error) {
    console.error('Unblock date error:', error);
    showStatus('Failed to unblock date: ' + error.message, 'error');
  }
}

// Load blocked dates (periods)
async function loadBlockedDates() {
  const listEl = document.getElementById('blocked-dates-list');
  if (!listEl) return;
  
  try {
    const params = new URLSearchParams({ action: 'get_blocked_dates' });
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (!response.ok) {
      throw new Error('Failed to load blocked dates');
    }
    
    // Проверяем, что ответ действительно JSON, а не HTML (ошибка PHP)
    const contentType = response.headers.get('content-type');
    let result = null;
    
    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text();
      console.error('API returned non-JSON response for blocked dates:', text.substring(0, 200));
      listEl.innerHTML = '<p style="color: #e53e3e;">Failed to load blocked dates: API returned invalid response</p>';
      return;
    }
    
    try {
      result = await response.json();
    } catch (jsonError) {
      console.error('Failed to parse blocked dates JSON:', jsonError);
      listEl.innerHTML = '<p style="color: #e53e3e;">Failed to load blocked dates: Invalid JSON response</p>';
      return;
    }
    
    listEl.innerHTML = '';
    
    if (result && result.success && result.data?.blocked_dates && result.data.blocked_dates.length > 0) {
      result.data.blocked_dates.forEach(blocked => {
        // Используем date_from/date_to если есть, иначе blocked_date для обратной совместимости
        const dateFrom = blocked.date_from || blocked.blocked_date || '';
        const dateTo = blocked.date_to || blocked.blocked_date || '';
        const reason = blocked.reason || '';
        
        // Отображаем "For all bookings" вместо "__all__"
        const displayRoomName = blocked.room_name === '__all__' ? 'For all bookings' : (blocked.room_name || '—');
        
        const item = document.createElement('div');
        item.className = 'blocked-date-item';
        item.innerHTML = `
          <div>
            <strong style="color: #000;">${escapeHtml(displayRoomName)}</strong>
            <div style="font-size: 14px; color: #718096; margin-top: 4px;">
              ${formatDate(dateFrom)} — ${formatDate(dateTo)}
              ${reason ? ` — ${escapeHtml(reason)}` : ''}
            </div>
          </div>
          <button class="admin-btn admin-btn-danger" onclick="unblockDate(${blocked.id})">Unblock</button>
        `;
        listEl.appendChild(item);
      });
    } else {
      listEl.innerHTML = '<p style="color: #718096; text-align: center; padding: 20px;">No blocked dates</p>';
    }
  } catch (error) {
    console.error('Load blocked dates error:', error);
    listEl.innerHTML = '<p style="color: #e53e3e;">Failed to load blocked dates</p>';
  }
}

// Initialize calendar blocking
function initCalendarBlocking() {
  const blockBtn = document.getElementById('block-date-btn');
  const refreshBtn = document.getElementById('calendar-refresh');
  const syncBtn = document.getElementById('airbnb-sync-btn');
  
  // Initialize Flatpickr for date inputs (same approach as room pages)
  const dateFromInput = document.getElementById('block-date-from');
  const dateToInput = document.getElementById('block-date-to');
  
  if (dateFromInput && typeof flatpickr !== 'undefined' && !dateFromInput.dataset.flatpickrInitialized) {
    // Mark as enhanced BEFORE Flatpickr initialization to prevent enhanceDateInputs from processing it
    dateFromInput.dataset.enhancedDate = '1';
    
    // Удаляем display proxy inputs от enhanceDateInputs, если они есть (чтобы избежать дублирования)
    const dateFromDisplay = dateFromInput.previousElementSibling && dateFromInput.previousElementSibling.tagName === 'INPUT' && dateFromInput.previousElementSibling.readOnly ? dateFromInput.previousElementSibling : null;
    if (dateFromDisplay) {
      dateFromDisplay.remove();
    }
    
    // Восстанавливаем нормальное состояние input для Flatpickr
    // Flatpickr сам стилизует input, поэтому его нужно оставить видимым
    dateFromInput.style.position = '';
    dateFromInput.style.opacity = '';
    dateFromInput.style.pointerEvents = '';
    dateFromInput.style.width = '';
    dateFromInput.style.height = '';
    dateFromInput.style.margin = '';
    dateFromInput.style.visibility = '';
    dateFromInput.style.clip = '';
    dateFromInput.removeAttribute('readonly');
    
    // Убеждаемся, что тип input остается "date" для HTML5 валидации
    dateFromInput.type = 'date';
    
    // Calculate date range: current year ± 3 years
    const currentYear = new Date().getFullYear();
    const minYear = currentYear - 3;
    const maxYear = currentYear + 3;
    const minDate = new Date(minYear, 0, 1); // January 1st of minYear
    const maxDate = new Date(maxYear, 11, 31); // December 31st of maxYear
    
    const fpFrom = flatpickr(dateFromInput, {
      dateFormat: 'Y-m-d',
      allowInput: false, // Отключаем прямой ввод в input
      clickOpens: true, // Открываем календарь при клике
      altInput: true, // Используем альтернативный input для отображения с placeholder
      altFormat: 'F j, Y', // Формат отображения даты (например: "November 7, 2025")
      placeholder: 'dd.mm.yyyy', // Плейсхолдер для altInput (соответствует формату браузера)
      minDate: minDate, // Minimum date (3 years ago)
      maxDate: maxDate, // Maximum date (3 years ahead)
      monthSelectorType: 'static', // Выпадающий список для месяцев
      yearSelectorType: 'static', // Выпадающий список для годов
      onReady: function(selectedDates, dateStr, instance) {
        // Убеждаемся, что родительский контейнер имеет position: relative
        const parent = instance.input.parentElement;
        if (parent && window.getComputedStyle(parent).position === 'static') {
          parent.style.position = 'relative';
        }
        
        // Скрываем оригинальный input, так как используем altInput для отображения
        // Убираем его далеко в сторону, чтобы он не перехватывал клики
        if (instance.input) {
          instance.input.style.position = 'absolute';
          instance.input.style.opacity = '0';
          instance.input.style.width = '0';
          instance.input.style.height = '0';
          instance.input.style.padding = '0';
          instance.input.style.margin = '0';
          instance.input.style.border = 'none';
          instance.input.style.pointerEvents = 'none';
          instance.input.style.left = '-9999px';
          instance.input.style.top = '-9999px';
          instance.input.style.visibility = 'visible'; // Видим для браузера, но невидим для пользователя
        }
        
        // Убеждаемся, что altInput имеет правильный размер и кликабельную область
        if (instance.altInput) {
          instance.altInput.style.width = '100%';
          instance.altInput.style.cursor = 'pointer';
          // Устанавливаем placeholder после полной инициализации
          if (!instance.altInput.value) {
            instance.altInput.placeholder = 'dd.mm.yyyy';
          }
        }
        
        // Применяем стили к стрелкам навигации
        setTimeout(() => {
          applyFlatpickrArrowStyles(instance);
        }, 50);
        setTimeout(() => {
          applyFlatpickrArrowStyles(instance);
        }, 200);
      },
      onOpen: function(selectedDates, dateStr, instance) {
        // Применяем стили к стрелкам каждый раз, когда календарь открывается
        setTimeout(() => {
          applyFlatpickrArrowStyles(instance);
        }, 50);
        setTimeout(() => {
          applyFlatpickrArrowStyles(instance);
        }, 200);
        
        // Отслеживаем появление выпадающего списка года
        const observer = new MutationObserver(() => {
          applyFlatpickrArrowStyles(instance);
        });
        
        if (instance.calendarContainer) {
          observer.observe(instance.calendarContainer, {
            childList: true,
            subtree: true
          });
          
          // Останавливаем наблюдение через 2 секунды
          setTimeout(() => {
            observer.disconnect();
          }, 2000);
        }
      },
      onChange: function(selectedDates, dateStr, instance) {
        // Убеждаемся, что значение реального input обновлено
        if (dateStr) {
          dateFromInput.value = dateStr;
          // Убеждаемся, что тип остается "date"
          dateFromInput.type = 'date';
          // Вызываем события для валидации формы
          dateFromInput.dispatchEvent(new Event('input', { bubbles: true }));
          dateFromInput.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
          dateFromInput.value = '';
          // Восстанавливаем placeholder если значение пустое
          if (instance.altInput) {
            instance.altInput.placeholder = 'dd.mm.yyyy';
            instance.altInput.value = '';
          }
          dateFromInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }
    });
    
    dateFromInput.dataset.flatpickrInitialized = '1';
    
    // Скрываем оригинальный input сразу после инициализации
    setTimeout(() => {
      if (fpFrom.input && fpFrom.altInput) {
        // Убеждаемся, что родительский контейнер имеет position: relative
        const parent = fpFrom.input.parentElement;
        if (parent && window.getComputedStyle(parent).position === 'static') {
          parent.style.position = 'relative';
        }
        
        // Убираем скрытый input далеко в сторону, чтобы он не перехватывал клики
        fpFrom.input.style.position = 'absolute';
        fpFrom.input.style.opacity = '0';
        fpFrom.input.style.width = '0';
        fpFrom.input.style.height = '0';
        fpFrom.input.style.padding = '0';
        fpFrom.input.style.margin = '0';
        fpFrom.input.style.border = 'none';
        fpFrom.input.style.pointerEvents = 'none';
        fpFrom.input.style.left = '-9999px';
        fpFrom.input.style.top = '-9999px';
        fpFrom.input.style.visibility = 'visible';
        
        // Убеждаемся, что altInput имеет правильный размер и кликабельную область
        fpFrom.altInput.style.width = '100%';
        fpFrom.altInput.style.cursor = 'pointer';
        
        // Убеждаемся, что placeholder установлен в altInput после инициализации
        if (!fpFrom.altInput.value) {
          fpFrom.altInput.placeholder = 'dd.mm.yyyy';
        }
      }
    }, 50);
  }
  
  if (dateToInput && typeof flatpickr !== 'undefined' && !dateToInput.dataset.flatpickrInitialized) {
    // Mark as enhanced BEFORE Flatpickr initialization to prevent enhanceDateInputs from processing it
    dateToInput.dataset.enhancedDate = '1';
    
    // Удаляем display proxy inputs от enhanceDateInputs, если они есть (чтобы избежать дублирования)
    const dateToDisplay = dateToInput.previousElementSibling && dateToInput.previousElementSibling.tagName === 'INPUT' && dateToInput.previousElementSibling.readOnly ? dateToInput.previousElementSibling : null;
    if (dateToDisplay) {
      dateToDisplay.remove();
    }
    
    // Восстанавливаем нормальное состояние input для Flatpickr
    // Flatpickr сам стилизует input, поэтому его нужно оставить видимым
    dateToInput.style.position = '';
    dateToInput.style.opacity = '';
    dateToInput.style.pointerEvents = '';
    dateToInput.style.width = '';
    dateToInput.style.height = '';
    dateToInput.style.margin = '';
    dateToInput.style.visibility = '';
    dateToInput.style.clip = '';
    dateToInput.removeAttribute('readonly');
    
    // Убеждаемся, что тип input остается "date" для HTML5 валидации
    dateToInput.type = 'date';
    
    // Calculate date range: current year ± 3 years
    const currentYear = new Date().getFullYear();
    const minYear = currentYear - 3;
    const maxYear = currentYear + 3;
    const minDate = new Date(minYear, 0, 1); // January 1st of minYear
    const maxDate = new Date(maxYear, 11, 31); // December 31st of maxYear
    
    const fpTo = flatpickr(dateToInput, {
      dateFormat: 'Y-m-d',
      allowInput: false, // Отключаем прямой ввод в input
      clickOpens: true, // Открываем календарь при клике
      altInput: true, // Используем альтернативный input для отображения с placeholder
      altFormat: 'F j, Y', // Формат отображения даты (например: "November 7, 2025")
      placeholder: 'dd.mm.yyyy', // Плейсхолдер для altInput (соответствует формату браузера)
      minDate: minDate, // Minimum date (3 years ago)
      maxDate: maxDate, // Maximum date (3 years ahead)
      monthSelectorType: 'static', // Выпадающий список для месяцев
      yearSelectorType: 'static', // Выпадающий список для годов
      onReady: function(selectedDates, dateStr, instance) {
        // Убеждаемся, что родительский контейнер имеет position: relative
        const parent = instance.input.parentElement;
        if (parent && window.getComputedStyle(parent).position === 'static') {
          parent.style.position = 'relative';
        }
        
        // Скрываем оригинальный input, так как используем altInput для отображения
        // Убираем его далеко в сторону, чтобы он не перехватывал клики
        if (instance.input) {
          instance.input.style.position = 'absolute';
          instance.input.style.opacity = '0';
          instance.input.style.width = '0';
          instance.input.style.height = '0';
          instance.input.style.padding = '0';
          instance.input.style.margin = '0';
          instance.input.style.border = 'none';
          instance.input.style.pointerEvents = 'none';
          instance.input.style.left = '-9999px';
          instance.input.style.top = '-9999px';
          instance.input.style.visibility = 'visible'; // Видим для браузера, но невидим для пользователя
        }
        
        // Убеждаемся, что altInput имеет правильный размер и кликабельную область
        if (instance.altInput) {
          instance.altInput.style.width = '100%';
          instance.altInput.style.cursor = 'pointer';
          // Устанавливаем placeholder после полной инициализации
          if (!instance.altInput.value) {
            instance.altInput.placeholder = 'dd.mm.yyyy';
          }
        }
        
        // Применяем стили к стрелкам навигации
        setTimeout(() => {
          applyFlatpickrArrowStyles(instance);
        }, 50);
        setTimeout(() => {
          applyFlatpickrArrowStyles(instance);
        }, 200);
      },
      onOpen: function(selectedDates, dateStr, instance) {
        // Применяем стили к стрелкам каждый раз, когда календарь открывается
        setTimeout(() => {
          applyFlatpickrArrowStyles(instance);
        }, 50);
        setTimeout(() => {
          applyFlatpickrArrowStyles(instance);
        }, 200);
        
        // Отслеживаем появление выпадающего списка года
        const observer = new MutationObserver(() => {
          applyFlatpickrArrowStyles(instance);
        });
        
        if (instance.calendarContainer) {
          observer.observe(instance.calendarContainer, {
            childList: true,
            subtree: true
          });
          
          // Останавливаем наблюдение через 2 секунды
          setTimeout(() => {
            observer.disconnect();
          }, 2000);
        }
      },
      onChange: function(selectedDates, dateStr, instance) {
        // Убеждаемся, что значение реального input обновлено
        if (dateStr) {
          dateToInput.value = dateStr;
          // Убеждаемся, что тип остается "date"
          dateToInput.type = 'date';
          // Вызываем события для валидации формы
          dateToInput.dispatchEvent(new Event('input', { bubbles: true }));
          dateToInput.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
          dateToInput.value = '';
          // Восстанавливаем placeholder если значение пустое
          if (instance.altInput) {
            instance.altInput.placeholder = 'dd.mm.yyyy';
            instance.altInput.value = '';
          }
          dateToInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }
    });
    
    dateToInput.dataset.flatpickrInitialized = '1';
    
    // Скрываем оригинальный input сразу после инициализации
    setTimeout(() => {
      if (fpTo.input && fpTo.altInput) {
        // Убеждаемся, что родительский контейнер имеет position: relative
        const parent = fpTo.input.parentElement;
        if (parent && window.getComputedStyle(parent).position === 'static') {
          parent.style.position = 'relative';
        }
        
        // Убираем скрытый input далеко в сторону, чтобы он не перехватывал клики
        fpTo.input.style.position = 'absolute';
        fpTo.input.style.opacity = '0';
        fpTo.input.style.width = '0';
        fpTo.input.style.height = '0';
        fpTo.input.style.padding = '0';
        fpTo.input.style.margin = '0';
        fpTo.input.style.border = 'none';
        fpTo.input.style.pointerEvents = 'none';
        fpTo.input.style.left = '-9999px';
        fpTo.input.style.top = '-9999px';
        fpTo.input.style.visibility = 'visible';
        
        // Убеждаемся, что altInput имеет правильный размер и кликабельную область
        fpTo.altInput.style.width = '100%';
        fpTo.altInput.style.cursor = 'pointer';
        
        // Убеждаемся, что placeholder установлен в altInput после инициализации
        if (!fpTo.altInput.value) {
          fpTo.altInput.placeholder = 'dd.mm.yyyy';
        }
      }
    }, 50);
  }
  
  // Инициализация вкладок комнат для календаря
  const roomTabs = document.querySelectorAll('.calendar-room-tab');
  roomTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      // Убираем активный класс со всех вкладок
      roomTabs.forEach(t => t.classList.remove('active'));
      // Добавляем активный класс к выбранной вкладке
      tab.classList.add('active');
      // Загружаем календарь для выбранной комнаты
      loadCalendarData();
    });
  });
  
  if (blockBtn) {
    blockBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      blockDate();
    });
  }
  
  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      loadCalendarData();
    });
  }
  
  // Навигация по календарю
  const prevBtn = document.getElementById('calendar-prev');
  const nextBtn = document.getElementById('calendar-next');
  
  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      calendarStartMonth -= 3;
      loadCalendarData();
    });
  }
  
  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      calendarStartMonth += 3;
      loadCalendarData();
    });
  }
  
  if (syncBtn) {
    syncBtn.addEventListener('click', syncAirbnbCalendar);
  }
  
  // Загружаем статус синхронизации при открытии раздела
  loadAirbnbSyncStatus();
}

// Синхронизация календаря Airbnb
async function syncAirbnbCalendar() {
  const syncBtn = document.getElementById('airbnb-sync-btn');
  const statusEl = document.getElementById('airbnb-sync-status');
  const statusTextEl = document.getElementById('airbnb-sync-status-text');
  
  if (!syncBtn || !statusEl || !statusTextEl) {
    return;
  }
  
  // Показываем состояние загрузки
  syncBtn.disabled = true;
  syncBtn.textContent = 'Syncing...';
  statusEl.style.display = 'block';
  statusTextEl.textContent = 'Syncing with Airbnb calendar...';
  statusTextEl.style.color = '#4299e1';
  
  try {
    const formData = new FormData();
    formData.append('action', 'sync_airbnb');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to sync Airbnb calendar');
    }
    
    const result = await response.json();
    
    if (result.success) {
      statusTextEl.textContent = `Sync completed successfully! ${result.data?.synced_rooms?.length || 0} room(s) synced.`;
      statusTextEl.style.color = '#2f855a';
      
      // Если были ошибки, показываем их
      if (result.data?.errors && result.data.errors.length > 0) {
        const errors = result.data.errors.map(e => `${e.room}: ${e.error}`).join(', ');
        statusTextEl.textContent += ` Errors: ${errors}`;
        statusTextEl.style.color = '#e53e3e';
      }
      
      // Обновляем календарь после синхронизации
      setTimeout(() => {
        loadCalendarData();
        loadAirbnbSyncStatus();
      }, 1000);
    } else {
      throw new Error(result.error || 'Sync failed');
    }
  } catch (error) {
    console.error('Sync Airbnb error:', error);
    statusTextEl.textContent = 'Failed to sync Airbnb calendar: ' + error.message;
    statusTextEl.style.color = '#e53e3e';
  } finally {
    syncBtn.disabled = false;
    syncBtn.textContent = 'Sync with Airbnb';
  }
}

// Загрузка статуса синхронизации Airbnb
async function loadAirbnbSyncStatus() {
  const statusEl = document.getElementById('airbnb-sync-status');
  const statusTextEl = document.getElementById('airbnb-sync-status-text');
  
  if (!statusEl || !statusTextEl) {
    return;
  }
  
  try {
    const params = new URLSearchParams({ action: 'get_airbnb_sync_status' });
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (response.ok) {
      const result = await response.json();
      
      if (result.success && result.data?.sync_status && result.data.sync_status.length > 0) {
        const status = result.data.sync_status;
        const statusText = status.map(s => {
          const lastSync = s.last_synced ? new Date(s.last_synced).toLocaleString() : 'Never';
          return `${s.room_name}: ${s.blocked_count} blocked dates, last synced: ${lastSync}`;
        }).join('<br>');
        
        // Last Sync Status display removed per user request
        // statusTextEl.innerHTML = `<strong>Last Sync Status:</strong><br>${statusText}`;
        statusTextEl.style.color = '#4a5568';
        statusEl.style.display = 'block';
      } else {
        statusTextEl.textContent = 'No sync status available. Click "Sync with Airbnb" to start syncing.';
        statusTextEl.style.color = '#718096';
        statusEl.style.display = 'block';
      }
    }
  } catch (error) {
    console.error('Load sync status error:', error);
    // Не показываем ошибку, просто скрываем статус
    statusEl.style.display = 'none';
  }
}

// ==========================================
// DASHBOARD STATS
// ==========================================

// Update dashboard statistics
// Initialize dashboard date filters
function initDashboardFilters(dashboardType) {
  const dateFromId = `${dashboardType}-dashboard-date-from`;
  const dateToId = `${dashboardType}-dashboard-date-to`;
  const resetId = `${dashboardType}-dashboard-reset-filter`;
  
  const dateFromInput = document.getElementById(dateFromId);
  const dateToInput = document.getElementById(dateToId);
  const resetBtn = document.getElementById(resetId);
  
  // Initialize Flatpickr for date filters
  if (typeof flatpickr !== 'undefined') {
    // Calculate date range: current year ± 3 years
    const currentYear = new Date().getFullYear();
    const minYear = currentYear - 3;
    const maxYear = currentYear + 3;
    const minDate = new Date(minYear, 0, 1); // January 1st of minYear
    const maxDate = new Date(maxYear, 11, 31); // December 31st of maxYear
    
    if (dateFromInput && !dateFromInput._flatpickr) {
      flatpickr(dateFromInput, {
        dateFormat: 'Y-m-d',
        allowInput: true,
        clickOpens: true,
        altInput: true,
        altFormat: 'F j, Y',
        minDate: minDate, // Minimum date (3 years ago)
        maxDate: maxDate, // Maximum date (3 years ahead)
        monthSelectorType: 'static', // Выпадающий список для месяцев
        yearSelectorType: 'static' // Выпадающий список для годов
      });
    }
    
    if (dateToInput && !dateToInput._flatpickr) {
      flatpickr(dateToInput, {
        dateFormat: 'Y-m-d',
        allowInput: true,
        clickOpens: true,
        altInput: true,
        altFormat: 'F j, Y',
        minDate: minDate, // Minimum date (3 years ago)
        maxDate: maxDate, // Maximum date (3 years ahead)
        monthSelectorType: 'static', // Выпадающий список для месяцев
        yearSelectorType: 'static' // Выпадающий список для годов
      });
    }
  }
  
  // Add event listeners for date changes
  if (dateFromInput) {
    dateFromInput.addEventListener('change', () => {
      if (dashboardType === 'bookings') {
        updateBookingsDashboardStats();
      } else if (dashboardType === 'content') {
        updateContentDashboardStats();
      } else if (dashboardType === 'accounts') {
        updateAccountsDashboardStats();
      }
    });
  }
  
  if (dateToInput) {
    dateToInput.addEventListener('change', () => {
      if (dashboardType === 'bookings') {
        updateBookingsDashboardStats();
      } else if (dashboardType === 'content') {
        updateContentDashboardStats();
      } else if (dashboardType === 'accounts') {
        updateAccountsDashboardStats();
      }
    });
  }
  
  // Reset button
  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      if (dateFromInput) {
        if (dateFromInput._flatpickr) {
          dateFromInput._flatpickr.clear();
        } else {
          dateFromInput.value = '';
        }
      }
      if (dateToInput) {
        if (dateToInput._flatpickr) {
          dateToInput._flatpickr.clear();
        } else {
          dateToInput.value = '';
        }
      }
      
      if (dashboardType === 'bookings') {
        updateBookingsDashboardStats();
      } else if (dashboardType === 'content') {
        updateContentDashboardStats();
      } else if (dashboardType === 'accounts') {
        updateAccountsDashboardStats();
      }
    });
  }
}

// Get dashboard date filter values
function getDashboardDateFilters(dashboardType) {
  const dateFromId = `${dashboardType}-dashboard-date-from`;
  const dateToId = `${dashboardType}-dashboard-date-to`;
  
  const dateFromInput = document.getElementById(dateFromId);
  const dateToInput = document.getElementById(dateToId);
  
  let dateFrom = null;
  let dateTo = null;
  
  if (dateFromInput) {
    if (dateFromInput._flatpickr && dateFromInput._flatpickr.selectedDates.length > 0) {
      dateFrom = dateFromInput._flatpickr.formatDate(dateFromInput._flatpickr.selectedDates[0], 'Y-m-d');
    } else if (dateFromInput.value) {
      dateFrom = dateFromInput.value;
    }
  }
  
  if (dateToInput) {
    if (dateToInput._flatpickr && dateToInput._flatpickr.selectedDates.length > 0) {
      dateTo = dateToInput._flatpickr.formatDate(dateToInput._flatpickr.selectedDates[0], 'Y-m-d');
    } else if (dateToInput.value) {
      dateTo = dateToInput.value;
    }
  }
  
  return { dateFrom, dateTo };
}

// Filter bookings by date range
function filterBookingsByDate(bookings, dateFrom, dateTo) {
  if (!dateFrom && !dateTo) {
    return bookings; // No filter, return all
  }
  
  return bookings.filter(booking => {
    let bookingDate = null;
    
    // For room bookings, use checkin_date
    if (booking.booking_type === 'room' && booking.checkin_date) {
      bookingDate = booking.checkin_date;
    }
    // For massage bookings, use massage_date
    else if (booking.booking_type === 'massage' && booking.massage_date) {
      bookingDate = booking.massage_date;
    }
    // Fallback to created_at
    else if (booking.created_at) {
      bookingDate = booking.created_at.split(' ')[0]; // Extract date part
    }
    
    if (!bookingDate) {
      return false; // No date available, exclude
    }
    
    // Check if booking date is within range
    if (dateFrom && bookingDate < dateFrom) {
      return false;
    }
    if (dateTo && bookingDate > dateTo) {
      return false;
    }
    
    return true;
  });
}

// Update Bookings Management Dashboard statistics
async function updateBookingsDashboardStats() {
  try {
    // Get date filters
    const { dateFrom, dateTo } = getDashboardDateFilters('bookings');
    
    // Load room bookings
    const params = new URLSearchParams({ action: 'get_bookings' });
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    let allBookings = [];
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data?.bookings) {
        allBookings = result.data.bookings.map(booking => ({
          ...booking,
          booking_type: 'room'
        }));
      }
    }
    
    // Load massage bookings
    try {
      const massageParams = new URLSearchParams({ action: 'get_massage_bookings' });
      if (dateFrom) massageParams.append('date_from', dateFrom);
      if (dateTo) massageParams.append('date_to', dateTo);
      
      const massageResponse = await fetch('api.php?' + massageParams.toString(), {
        method: 'GET'
      });
      
      if (massageResponse.ok) {
        const massageContentType = massageResponse.headers.get('content-type');
        if (massageContentType && massageContentType.includes('application/json')) {
          try {
            const massageResult = await massageResponse.json();
            if (massageResult.success && massageResult.data?.bookings) {
              const massageBookings = massageResult.data.bookings.map(booking => ({
                ...booking,
                booking_type: 'massage'
              }));
              allBookings = [...allBookings, ...massageBookings];
            }
          } catch (jsonError) {
            console.error('Failed to parse massage bookings JSON in dashboard:', jsonError);
          }
        }
      }
    } catch (massageError) {
      console.warn('Failed to load massage bookings for dashboard:', massageError);
    }
    
    // Apply date filter if needed (client-side fallback)
    allBookings = filterBookingsByDate(allBookings, dateFrom, dateTo);
    
    // Calculate statistics for all bookings (rooms + massage)
    const pending = allBookings.filter(b => b.status === 'pending').length;
    const confirmed = allBookings.filter(b => {
      if (b.booking_type === 'massage') {
        return b.status === 'confirmed';
      } else {
        return b.status === 'confirmed' && (!b.payment_status || b.payment_status !== 'paid');
      }
    }).length;
    const cancelled = allBookings.filter(b => b.status === 'cancelled').length;
    const total = allBookings.length;
    
    // Update UI
    const pendingEl = document.getElementById('pending-bookings');
    const totalEl = document.getElementById('total-bookings');
    const confirmedEl = document.getElementById('confirmed-bookings');
    const cancelledEl = document.getElementById('cancelled-bookings');
    
    if (pendingEl) pendingEl.textContent = pending;
    if (totalEl) totalEl.textContent = total;
    if (confirmedEl) confirmedEl.textContent = confirmed;
    if (cancelledEl) cancelledEl.textContent = cancelled;
  } catch (error) {
    console.error('Error updating bookings dashboard stats:', error);
  }
}

// Update Content Management Dashboard statistics
async function updateContentDashboardStats() {
  try {
    // Get date filters
    const { dateFrom, dateTo } = getDashboardDateFilters('content');
    
    // Update rooms count
    const roomsResponse = await fetch('api.php?action=get_rooms');
    if (roomsResponse.ok) {
      const roomsResult = await roomsResponse.json();
      if (roomsResult.success && roomsResult.data) {
        const totalRooms = roomsResult.data.length;
        const roomsEl = document.getElementById('total-rooms');
        if (roomsEl) roomsEl.textContent = totalRooms;
      }
    }
    
    // Update massage services count (if available)
    // Update yoga services count (if available)
    
    // Note: Content dashboard doesn't have date-based statistics yet
    // This is a placeholder for future date filtering if needed
    // Update last updated time
    const lastUpdatedEl = document.getElementById('last-updated');
    if (lastUpdatedEl) {
      lastUpdatedEl.textContent = new Date().toLocaleString();
    }
  } catch (error) {
    console.error('Error updating content dashboard stats:', error);
  }
}

// Update Account Management Dashboard statistics
async function updateAccountsDashboardStats() {
  try {
    // Get date filters
    const { dateFrom, dateTo } = getDashboardDateFilters('accounts');
    
    const formData = new FormData();
    formData.append('action', 'get_users');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        let users = result.data;
        
        // Ensure users is an array
        if (!Array.isArray(users)) {
          console.error('Users data is not an array:', users);
          users = [];
        }
        
        // Filter users by date range if filters are set
        if (dateFrom || dateTo) {
          users = users.filter(u => {
            if (!u.created_at) return false;
            const createdDate = u.created_at.split(' ')[0]; // Extract date part
            
            if (dateFrom && createdDate < dateFrom) return false;
            if (dateTo && createdDate > dateTo) return false;
            
            return true;
          });
        }
        
        // Calculate statistics
        const total = users.length;
        const verified = users.filter(u => u.is_verified).length;
        
        // New this month (only if no date filter is set, otherwise show filtered count)
        let newThisMonth = 0;
        if (!dateFrom && !dateTo) {
          const now = new Date();
          const currentMonth = now.getMonth();
          const currentYear = now.getFullYear();
          newThisMonth = users.filter(u => {
            const created = new Date(u.created_at);
            return created.getMonth() === currentMonth && created.getFullYear() === currentYear;
          }).length;
        } else {
          // If date filter is set, show count of users in filtered range
          newThisMonth = users.length;
        }
        
        // Active this week
        const weekAgo = new Date(new Date().getTime() - 7 * 24 * 60 * 60 * 1000);
        const activeThisWeek = users.filter(u => {
          if (!u.last_session) return false;
          const lastSession = new Date(u.last_session);
          return lastSession >= weekAgo;
        }).length;
        
        // Update UI
        const totalEl = document.getElementById('total-accounts');
        const verifiedEl = document.getElementById('verified-accounts');
        const newMonthEl = document.getElementById('new-accounts-month');
        const activeWeekEl = document.getElementById('active-accounts-week');
        
        if (totalEl) totalEl.textContent = total;
        if (verifiedEl) verifiedEl.textContent = verified;
        if (newMonthEl) newMonthEl.textContent = newThisMonth;
        if (activeWeekEl) activeWeekEl.textContent = activeThisWeek;
      }
    }
  } catch (error) {
    console.error('Error updating accounts dashboard stats:', error);
  }
}

// Legacy function for backward compatibility
async function updateDashboardStats() {
  try {
    const params = new URLSearchParams({ action: 'get_bookings' });
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data?.bookings) {
        const bookings = result.data.bookings;
        const pendingCount = bookings.filter(b => b.status === 'pending').length;
        const totalCount = bookings.length;
        
        const pendingEl = document.getElementById('pending-bookings');
        const totalEl = document.getElementById('total-bookings');
        
        if (pendingEl) pendingEl.textContent = pendingCount;
        if (totalEl) totalEl.textContent = totalCount;
      }
    }
  } catch (error) {
    console.error('Update dashboard stats error:', error);
  }
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function formatDate(dateString) {
  if (!dateString) return '—';
  try {
    const date = new Date(dateString + 'T00:00:00');
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 
                   'July', 'August', 'September', 'October', 'November', 'December'];
    return `${months[date.getMonth()]} ${String(date.getDate()).padStart(2, '0')}, ${date.getFullYear()}`;
  } catch (e) {
    return dateString;
  }
}

function formatDateTime(dateString) {
  if (!dateString) return '—';
  try {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  } catch (e) {
    return dateString;
  }
}

function formatDateString(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

// parseLocalDate - парсинг даты YYYY-MM-DD как локальной даты (без часового пояса)
// Используется для избежания проблем с часовыми поясами
function parseLocalDate(iso) {
  if (!iso) return null;
  const parts = String(iso).split('-');
  if (parts.length !== 3) return null;
  const y = parseInt(parts[0], 10);
  const m = parseInt(parts[1], 10) - 1;
  const d = parseInt(parts[2], 10);
  if (isNaN(y) || isNaN(m) || isNaN(d)) return null;
  return new Date(y, m, d);
}

function isDateBooked(dateString, bookings) {
  const checkDate = new Date(dateString + 'T00:00:00');
  
  return bookings.some(booking => {
    const checkin = new Date(booking.checkin_date + 'T00:00:00');
    const checkout = new Date(booking.checkout_date + 'T00:00:00');
    return checkDate >= checkin && checkDate < checkout;
  });
}

// Получить информацию о бронировании для конкретной даты
function getBookingInfoForDate(dateString, bookings) {
  if (!bookings || bookings.length === 0) {
    return null;
  }
  
  // Используем parseLocalDate для правильной обработки дат без часового пояса
  const checkDate = parseLocalDate(dateString);
  if (!checkDate) {
    console.warn('Invalid dateString in getBookingInfoForDate:', dateString);
    return null;
  }
  
  for (const booking of bookings) {
    if (!booking.checkin_date || !booking.checkout_date) {
      continue;
    }
    
    const checkin = parseLocalDate(booking.checkin_date);
    const checkout = parseLocalDate(booking.checkout_date);
    
    if (!checkin || !checkout) {
      console.warn('Invalid dates in booking:', booking.id, { checkin: booking.checkin_date, checkout: booking.checkout_date });
      continue;
    }
    
    // Проверяем, попадает ли дата в период бронирования (checkin <= date < checkout)
    if (checkDate >= checkin && checkDate < checkout) {
      return booking;
    }
  }
  
  return null;
}

function getMassageBookingInfoForDate(dateString, massageBookings) {
  for (const booking of massageBookings) {
    if (booking.massage_date === dateString) {
      return booking;
    }
  }
  
  return null;
}

// Account Management functions
let allAccounts = [];
let filteredAccounts = [];

// Load accounts data
async function loadAccountsData() {
  const loadingEl = document.getElementById('accounts-loading');
  const listEl = document.getElementById('accounts-list');
  const emptyEl = document.getElementById('accounts-empty');
  
  if (loadingEl) loadingEl.style.display = 'block';
  if (listEl) listEl.style.display = 'none';
  if (emptyEl) emptyEl.style.display = 'none';
  
  try {
    const formData = new FormData();
    formData.append('action', 'get_users');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        allAccounts = result.data;
        filteredAccounts = [...allAccounts];
        renderAccountsList();
        populateYearFilter();
      } else {
        allAccounts = [];
        filteredAccounts = [];
        renderAccountsList();
      }
    } else {
      console.error('Failed to load accounts');
      allAccounts = [];
      filteredAccounts = [];
      renderAccountsList();
    }
  } catch (error) {
    console.error('Error loading accounts:', error);
    allAccounts = [];
    filteredAccounts = [];
    renderAccountsList();
  } finally {
    if (loadingEl) loadingEl.style.display = 'none';
  }
}

// Render accounts list
function renderAccountsList() {
  const listEl = document.getElementById('accounts-list');
  const emptyEl = document.getElementById('accounts-empty');
  
  if (!listEl || !emptyEl) return;
  
  if (filteredAccounts.length === 0) {
    listEl.style.display = 'none';
    emptyEl.style.display = 'block';
    return;
  }
  
  listEl.style.display = 'block';
  emptyEl.style.display = 'none';
  
  listEl.innerHTML = filteredAccounts.map(user => {
    const createdDate = new Date(user.created_at);
    const formattedDate = createdDate.toLocaleDateString('en-US', { 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    });
    
    const lastSession = user.last_session ? new Date(user.last_session).toLocaleDateString('en-US', { 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    }) : 'Never';
    
    return `
      <div class="user-card" data-user-id="${user.id}">
        <div class="user-card-header">
          <h3>${escapeHtml(user.name)}</h3>
          <button class="admin-btn admin-btn-danger" onclick="deleteUser(${user.id})">Delete</button>
        </div>
        <div class="user-details-grid">
          <div class="user-detail-item">
            <span class="user-detail-label">Email</span>
            <span class="user-detail-value">${escapeHtml(user.email)}</span>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Phone</span>
            <span class="user-detail-value">${escapeHtml(user.phone || 'N/A')}</span>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Additional Phone</span>
            <span class="user-detail-value">${escapeHtml(user.phone2 || 'N/A')}</span>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Registered</span>
            <span class="user-detail-value">${formattedDate}</span>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Last Session</span>
            <span class="user-detail-value">${lastSession}</span>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Verified</span>
            <span class="user-detail-value">${user.is_verified ? 'Yes' : 'No'}</span>
          </div>
        </div>
      </div>
    `;
  }).join('');
}

// Populate year filter
function populateYearFilter() {
  const yearSelect = document.getElementById('accounts-filter-year');
  if (!yearSelect) return;
  
  const years = new Set();
  allAccounts.forEach(user => {
    const year = new Date(user.created_at).getFullYear();
    years.add(year);
  });
  
  const sortedYears = Array.from(years).sort((a, b) => b - a);
  
  yearSelect.innerHTML = '<option value="">All Years</option>' + 
    sortedYears.map(year => `<option value="${year}">${year}</option>`).join('');
}

// Initialize accounts filters
function initAccountsFilters() {
  const applyBtn = document.getElementById('accounts-filter-apply');
  const resetBtn = document.getElementById('accounts-filter-reset');
  const refreshBtn = document.getElementById('accounts-refresh');
  const copyEmailsBtn = document.getElementById('accounts-copy-emails');
  const copyPhonesBtn = document.getElementById('accounts-copy-phones');
  const searchInput = document.getElementById('accounts-search-name');
  
  if (applyBtn) {
    applyBtn.addEventListener('click', applyAccountsFilters);
  }
  
  if (resetBtn) {
    resetBtn.addEventListener('click', resetAccountsFilters);
  }
  
  if (refreshBtn) {
    refreshBtn.addEventListener('click', loadAccountsData);
  }
  
  if (copyEmailsBtn) {
    copyEmailsBtn.addEventListener('click', copyAllEmails);
  }
  
  if (copyPhonesBtn) {
    copyPhonesBtn.addEventListener('click', copyAllPhones);
  }
  
  if (searchInput) {
    searchInput.addEventListener('keyup', (e) => {
      if (e.key === 'Enter') {
        applyAccountsFilters();
      }
    });
  }
}

// Apply accounts filters
function applyAccountsFilters() {
  const searchName = document.getElementById('accounts-search-name')?.value.trim().toLowerCase() || '';
  const filterMonth = document.getElementById('accounts-filter-month')?.value || '';
  const filterYear = document.getElementById('accounts-filter-year')?.value || '';
  
  filteredAccounts = allAccounts.filter(user => {
    // Name search
    if (searchName && !user.name.toLowerCase().includes(searchName)) {
      return false;
    }
    
    // Month filter
    if (filterMonth) {
      const userMonth = String(new Date(user.created_at).getMonth() + 1).padStart(2, '0');
      if (userMonth !== filterMonth) {
        return false;
      }
    }
    
    // Year filter
    if (filterYear) {
      const userYear = new Date(user.created_at).getFullYear().toString();
      if (userYear !== filterYear) {
        return false;
      }
    }
    
    return true;
  });
  
  renderAccountsList();
}

// Reset accounts filters
function resetAccountsFilters() {
  const searchInput = document.getElementById('accounts-search-name');
  const monthSelect = document.getElementById('accounts-filter-month');
  const yearSelect = document.getElementById('accounts-filter-year');
  
  if (searchInput) searchInput.value = '';
  if (monthSelect) monthSelect.value = '';
  if (yearSelect) yearSelect.value = '';
  
  filteredAccounts = [...allAccounts];
  renderAccountsList();
}

// Copy all emails
async function copyAllEmails() {
  const emails = filteredAccounts.map(user => user.email).filter(email => email).join('\n');
  
  if (!emails) {
    alert('No emails to copy');
    return;
  }
  
  try {
    await navigator.clipboard.writeText(emails);
    alert(`Copied ${filteredAccounts.length} email(s) to clipboard`);
  } catch (error) {
    console.error('Failed to copy emails:', error);
    alert('Failed to copy emails to clipboard');
  }
}

// Copy all phones
async function copyAllPhones() {
  const phones = filteredAccounts
    .map(user => {
      const phones = [];
      if (user.phone) phones.push(user.phone);
      if (user.phone2) phones.push(user.phone2);
      return phones;
    })
    .flat()
    .filter(phone => phone)
    .join('\n');
  
  if (!phones) {
    alert('No phones to copy');
    return;
  }
  
  try {
    await navigator.clipboard.writeText(phones);
    const phoneCount = phones.split('\n').length;
    alert(`Copied ${phoneCount} phone number(s) to clipboard`);
  } catch (error) {
    console.error('Failed to copy phones:', error);
    alert('Failed to copy phones to clipboard');
  }
}

// Delete user
async function deleteUser(userId) {
  if (!confirm('Are you sure you want to delete this user account? This action cannot be undone.')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'delete_user');
    formData.append('user_id', userId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success) {
        // Remove from arrays
        allAccounts = allAccounts.filter(user => user.id !== userId);
        filteredAccounts = filteredAccounts.filter(user => user.id !== userId);
        renderAccountsList();
        alert('User account deleted successfully');
      } else {
        alert('Failed to delete user account: ' + (result.error || 'Unknown error'));
      }
    } else {
      alert('Failed to delete user account');
    }
  } catch (error) {
    console.error('Error deleting user:', error);
    alert('Error deleting user account');
  }
}

// Escape HTML
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Export functions for global access
window.showSection = showSection;
window.deleteUser = deleteUser;
window.editRoom = (index) => { /* TODO: Implement edit room */ };
window.deleteRoom = (index) => { /* TODO: Implement delete room */ };
window.editMassage = (index) => { /* TODO: Implement edit massage */ };
window.deleteMassage = (index) => { /* TODO: Implement delete massage */ };
window.editYoga = (index) => { /* TODO: Implement edit yoga */ };
window.deleteYoga = (index) => { /* TODO: Implement delete yoga */ };
window.deleteImage = (index) => { /* TODO: Implement delete image */ };
// Massage booking management functions
async function confirmMassageBooking(bookingId) {
  if (!confirm('Are you sure you want to confirm this massage booking?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'confirm_massage_booking');
    formData.append('booking_id', bookingId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to confirm massage booking');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Massage booking confirmed successfully!');
      loadBookingsData();
      updateBookingsDashboardStats();
    } else {
      throw new Error(result.error || 'Failed to confirm massage booking');
    }
  } catch (error) {
    console.error('Confirm massage booking error:', error);
    showStatus('Failed to confirm massage booking: ' + error.message, 'error');
  }
}

async function cancelMassageBooking(bookingId) {
  const reason = prompt('Please enter a reason for cancellation (optional):');
  if (reason === null) {
    return; // User cancelled
  }
  
  if (!confirm('Are you sure you want to cancel this massage booking?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'cancel_massage_booking');
    formData.append('booking_id', bookingId);
    if (reason) {
      formData.append('reason', reason);
    }
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to cancel massage booking');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Massage booking cancelled successfully!');
      loadBookingsData();
      updateBookingsDashboardStats();
    } else {
      throw new Error(result.error || 'Failed to cancel massage booking');
    }
  } catch (error) {
    console.error('Cancel massage booking error:', error);
    showStatus('Failed to cancel massage booking: ' + error.message, 'error');
  }
}

async function deleteMassageBooking(bookingId) {
  if (!confirm('Are you sure you want to delete this massage booking? This action cannot be undone.')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'delete_massage_booking');
    formData.append('booking_id', bookingId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to delete massage booking');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Massage booking deleted successfully!');
      loadBookingsData();
      updateBookingsDashboardStats();
    } else {
      throw new Error(result.error || 'Failed to delete massage booking');
    }
  } catch (error) {
    console.error('Delete massage booking error:', error);
    showStatus('Failed to delete massage booking: ' + error.message, 'error');
  }
}

// Make functions globally available
window.confirmBooking = confirmBooking;
window.cancelBooking = cancelBooking;
window.deleteBooking = deleteBooking;
window.viewBookingDetails = viewBookingDetails;
window.unblockDate = unblockDate;
window.confirmMassageBooking = confirmMassageBooking;
window.cancelMassageBooking = cancelMassageBooking;
window.deleteMassageBooking = deleteMassageBooking;





