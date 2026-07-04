<?php
$configDir = 'config';
$mapUrl = null;
$factoryUrlParam = $_GET['factory'] ?? null;
if (!$factoryUrlParam) {
    // If not set via URL, we default to 1st Floor or let JS override it. 
    // Wait, JS will fetch dynamically. Here we just want a preload hint if possible.
    // It's safer to just default 2nd Floor for preload since JS will handle the real image.
    $factoryUrlParam = '1st Floor';
}
$factoryDir = $configDir . '/' . $factoryUrlParam;
if (file_exists($factoryDir)) {
    $files = glob($factoryDir . '/FloorPlan.*');
    if ($files) {
        foreach ($files as $f) {
            if (!preg_match('/FloorPlan_\d{4}/', basename($f))) {
                $mapUrl = $f . '?v=' . filemtime($f);
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MT5200 Patrol</title>
    <!--  Dev by : MT5653-DX Team / Naphatsawan Ankard -->
    <!--  Last updated : 25 May-2026-->
    <!-- Favicon -->
     <!-- Tom Select -->

    
    <link rel="icon"
        href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23DC2626%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22><path d=%22M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z%22/><path d=%22M7 7h.01%22/></svg>">



    <!-- Tailwind CSS -->
    <link href="./dist/output.css?v=<?= filemtime('./dist/output.css') ?>" rel="stylesheet">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="libs/leaflet/leaflet.css?v=<?= filemtime('libs/leaflet/leaflet.css') ?>">

    <!-- Lucide Icons -->
    <script src="libs/lucide/lucide.min.js" defer></script>

    <style>
        html,
        body {
            height: 100%;
            margin: 0;
            overflow: hidden;
        }

        .custom-marker svg {
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }

        /* Collapsible Sidebar */
        #sidebar {
            width: 20rem;
            min-width: 20rem;
            max-width: 20rem;
            overflow: hidden;
            transition: min-width 0.3s ease, max-width 0.3s ease;
            flex-shrink: 0;
        }

        #sidebar.collapsed {
            min-width: 0 !important;
            max-width: 0 !important;
        }

        #toggleSidebarBtn {
            position: fixed;
            bottom: 20px;
            top: auto;
            left: 20px;
            z-index: 20000;
            background: #374151;
            border: 1px solid #1f2937;
            border-radius: 6px;
            padding: 6px 8px;
            cursor: pointer;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, left 0.3s ease;
            color: white;
            font-size: 18px;
            line-height: 1;
        }

        #toggleSidebarBtn:hover {
            background: #1f2937;
        }

        /* Leaflet zoom control dark style */
        .leaflet-control-zoom a {
            background-color: #374151 !important;
            color: white !important;
            border-color: #4b5563 !important;
        }

        .leaflet-control-zoom a:hover {
            background-color: #1f2937 !important;
            color: white !important;
        }
        /* ===== Zone Filter Fixed Width ===== */
        #filterZone {
            width: 190px !important;
            max-width: 190px !important;
        }

        #tagList {
            width: 100%;
            max-width: 100%;
        }
    </style>
</head>

<body class="bg-gray-50 overflow-hidden">
    <div class="flex h-screen w-screen">

    <!-- Sidebar -->
    <div id="sidebar" class="bg-white shadow-lg flex flex-col z-10 h-full border-r border-gray-200">
        <!-- Header -->
        <div class="p-4 bg-red-700 text-white shadow-md relative">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h1 class="text-xl font-bold flex items-center gap-2">
                    <i data-lucide="layout"></i>
                    MT5200 Patrol
                </h1>
                <a href="dashboard.php" title="Open Dashboard"
                    style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:6px;background:rgba(255,255,255,0.15);color:#fff;font-size:0.78rem;font-weight:600;text-decoration:none;border:1px solid rgba(255,255,255,0.25);white-space:nowrap;transition:background .2s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.28)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                    <i data-lucide="bar-chart-2" style="width:14px;height:14px;"></i>
                    Dashboard
                </a>
            </div>
            <p class="text-red-100 text-[10px] mt-2 opacity-80 leading-4">
                Create by MT5653-DX<br>
                Developed by Kridsada MT5252
            </p>
            <span style="position:absolute; right:16px; bottom:18px; font-size:9px; color:#fecaca; opacity:0.9;">
                Ver. 3.0.0
            </span>
            
        </div>

        <!-- Upload Control -->
        <div class="p-4 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
            <button id="userGuideBtn" title="User Guide"
                class="flex items-center justify-center flex-1 px-3 py-2 bg-blue-50 border border-blue-200 rounded-md shadow-sm hover:bg-blue-100 text-sm font-medium text-blue-700 transition-colors">
                <i data-lucide="book-open" class="w-4 h-4 mr-1"></i>
                <span class="hidden sm:inline">User Guide</span>
            </button>
            <button id="mapUploadBtn" title="Change Floor Layout"
                class="flex items-center justify-center flex-1 px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm cursor-pointer hover:bg-gray-50 text-sm font-medium text-gray-700 transition-colors">
                <i data-lucide="image" class="w-4 h-4 mr-1"></i>
                <span class="hidden sm:inline">Layout</span>
            </button>
            <button id="exportBtn" title="Export Data"
                class="flex items-center justify-center flex-1 px-3 py-2 bg-green-50 border border-green-200 rounded-md shadow-sm hover:bg-green-100 text-sm font-medium text-green-700 transition-colors">
                <i data-lucide="download" class="w-4 h-4 mr-1"></i>
                <span class="hidden sm:inline">Export</span>
            </button>
        </div>

        <!-- Filter Control -->
        <div class="p-2 bg-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider pl-4 pr-2">
            <button id="toggleActiveTagsBtn" type="button"
                class="w-full flex justify-between items-center mb-1 text-left">
                <span>Active Tags (<span id="tagCount">0</span>)</span>
                <span id="activeTagsArrow">▼</span>
            </button>
            <div id="activeTagsFilterPanel">
            <!-- Factory -->
            <div class="flex items-center gap-2 bg-red-800/50 p-2 rounded-md border border-red-600/50 mt-1">
                <label for="factorySelect"
                    class="text-xs font-semibold uppercase tracking-wider flex items-center gap-1">
                    <i data-lucide="building-2" style="width:14px;height:14px;"></i>
                    Factory 17:
                </label>
                <select id="factorySelect"
                    class="flex-1 text-sm bg-white text-gray-800 border-0 rounded px-2 py-1 font-medium focus:ring-2 focus:ring-red-400 outline-none cursor-pointer">
                    <option value="1st Floor">1st Floor</option>
                    <option value="2nd Floor" selected>2nd Floor</option>
                </select>
            </div>
            
            <div class="space-y-2 mt-2">

                <!-- Status -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase">
                        Status
                    </label>
                    <select id="filterSelect"
                        class="w-full text-xs border border-gray-300 rounded px-2 py-1 bg-white">
                        <option value="All">All Status</option>
                        <option value="Open">Open</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Need help">Need help</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>

                <!-- Inspection Type -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase">
                        Inspection Type
                    </label>
                    <select id="filterInspectionType"
                         class="w-full text-xs border border-gray-300 rounded px-2 py-1 bg-white">
                         <option value="All">All Type</option>
                         <option value="5S">5S</option>
                         <option value="Safety">Safety</option>
                    </select>
                </div>

                <!-- Zone -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase">
                        Zone
                    </label>
                    <select id="filterZone"
                        class="w-full text-xs border border-gray-300 rounded px-2 py-1 bg-white">
                        <option value="All">All Zones</option>
                    </select>
                </div>
                <!-- Month -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase">
                        Month
                    </label>
                    <select id="filterMonth"
                        class="w-full text-xs border border-gray-300 rounded px-2 py-1 bg-white">
                    </select>
                </div>

                <!-- Year -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase">
                        Year
                    </label>
                    <select id="filterYear"
                        class="w-full text-xs border border-gray-300 rounded px-2 py-1 bg-white">
                    </select>
                </div>
            </div>
            </div>
            </div>

        <!-- Tag List -->
        <div id="activeTagsPanel" class="flex-1 overflow-hidden">
            <div id="tagList" class="h-full overflow-y-auto p-4 space-y-3 w-full max-w-full">
            <!-- List items will be injected here -->
            </div> 
        </div>
    </div>

    <!-- Main Map Area -->
    <div class="flex-1 relative h-full bg-gray-100 overflow-hidden">
        <!-- Sidebar Toggle Button -->
        <button id="toggleSidebarBtn" title="Toggle Sidebar">&#9776;</button>

        <!-- Floating System Title -->
        <div style="left: 50%; transform: translateX(-50%);"
            class="absolute top-4 z-[1000] bg-white/95 backdrop-blur-sm px-6 py-2.5 rounded-full shadow-md border border-gray-200 pointer-events-none flex items-center gap-2 transition-all opacity-90">
            <i data-lucide="tags" class="text-red-600 w-5 h-5"></i>
            <h2 class="text-gray-800 font-bold text-lg tracking-tight select-none">
                MT5200 Patrol <span class="text-gray-300 mx-2 font-light">|</span> <span
                    class="text-gray-600 font-medium text-base">Floor Map</span>
            </h2>
        </div>

        <div id="map" class="h-full w-full"></div>

        <!-- Empty State (Placeholder when no map) -->
        <div id="emptyState" class="absolute inset-0 flex items-center justify-center bg-gray-100 z-[1000] hidden">
            <div class="text-center p-8">
                <p class="text-xl text-gray-500 mb-4">Please upload a factory floor plan to start.</p>
            </div>
        </div>
    </div>

    <!-- Modal Form -->
    <div id="tagModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[9999] p-4 hidden">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md max-h-[90vh] flex flex-col">
            <div class="sticky top-0 z-20 flex justify-between items-center p-4 border-b bg-white">
                <h2 id="modalTitle" class="text-xl font-bold text-gray-800">New Red Tag</h2>

                <div class="flex items-center gap-2">
                    <button id="deleteTagBtn" type="button"
                        class="hidden text-gray-400 hover:text-red-600 p-2 rounded hover:bg-red-50"
                        title="Delete Tag">
                        <i data-lucide="trash-2"></i>
                    </button>

                    <button id="closeModalBtn" type="button"
                        class="text-gray-500 hover:text-gray-700 p-2 rounded hover:bg-gray-100">
                        <i data-lucide="x"></i>
                    </button>
                </div>
            </div>

            <form id="tagForm" class="flex-1 overflow-y-auto p-4 space-y-4">
                <input type="hidden" id="tagId">
                <input type="hidden" id="tagX">
                <input type="hidden" id="tagY">

                <!-- Inspection Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Inspection Type <span class="text-red-500">*</span>
                    </label>
                    <select id="inspectionType" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="" disabled selected hidden>
                            Select inspection type...
                        </option>
                        
                        <option value="5S">5S</option>
                        <option value="Safety">Safety</option>
                    </select>
                </div>

                <!-- Zone -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Zone <span class="text-red-500">*</span>
                    </label>
                <input type="text" id="zoneSearch"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                    placeholder="Search Zone..." autocomplete="off">

                <input type="hidden" id="zone" required>

                <div id="zoneSuggestions"
                    class="hidden border border-gray-300 rounded-md bg-white max-h-48 overflow-y-auto text-sm mt-1 z-[10001]">
                </div>
                </div>

                <!-- Production Line -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Production Line</label>
                    <input type="text" id="productionLine"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                        placeholder="e.g. Line A, Machine 2">
                </div>

                <!-- Photo (Before) Upload -->
               <div>
                   <label class="block text-sm font-medium text-gray-700 mb-2">
                       Before Image <span class="text-red-500">*</span>
                   </label>

                   <div class="flex gap-3 mb-3">
                       <button type="button" id="takePhotoBtn"
                           class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-lg font-semibold">
                           📷 Take Photo
                       </button>

                       <button type="button" id="chooseGalleryBtn"
                           class="flex-1 px-4 py-3 bg-gray-100 border border-gray-300 rounded-lg font-semibold">
                           🖼️ Upload
                       </button>
                   </div>

                   <input type="file" id="tagImageInputCamera" accept="image/*" capture="environment" class="hidden">
                   <input type="file" id="tagImageInputGallery" accept="image/*" class="hidden">

                   <div class="border-2 border-dashed border-gray-300 rounded-lg h-48 flex items-center justify-center text-gray-500 bg-gray-50">
                       <img id="imagePreview" class="hidden w-full h-full object-contain rounded-lg">
                       <div id="uploadPlaceholder" class="text-center">
                           <div class="text-4xl mb-2">📷</div>
                           <div>No Image</div>
                       </div>
                   </div>
               </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Description / Problem <span class="text-red-500">*</span>
                    </label>
                    <textarea id="description" required rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                        placeholder="Describe the issue..."></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <select id="status" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="Open">Open</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Need help">Need help</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>

                <!-- Category Field -->
                <div id="categoryGroup">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Category <span class="text-red-500">*</span>
                    </label>
                    <select id="category" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="">Select Category</option>
                        <option value="Seiri (สะสาง)">Seiri (สะสาง)</option>
                        <option value="Seiton (สะดวก)">Seiton (สะดวก)</option>
                        <option value="Seisou (สะอาด)">Seisou (สะอาด)</option>
                        <option value="Seiketsu (สุขลักษณะ)">Seiketsu (สุขลักษณะ)</option>
                        <option value="Shitsuke (สร้างนิสัย)">Shitsuke (สร้างนิสัย)</option>
                    </select>
                </div>

                <!-- PIC Field (New) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        PIC
                    </label>
                    <input type="text" id="pic"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                        placeholder="Person In Charge">
                </div>

                <!-- Photo (After) Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        After Image
                        <span id="photoAfterRequired" class="text-red-500 hidden">*</span>
                    </label>

                    <div class="flex gap-3 mb-3">
                        <button type="button" id="takePhotoAfterBtn"
                            class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-lg font-semibold">
                            📷 Take Photo
                        </button>

                        <button type="button" id="chooseGalleryAfterBtn"
                            class="flex-1 px-4 py-3 bg-gray-100 border border-gray-300 rounded-lg font-semibold">
                            🖼️ Upload
                        </button>
                    </div>

                    <input type="file" id="tagImageInputAfterCamera" accept="image/*" capture="environment" class="hidden">
                    <input type="file" id="tagImageInputAfterGallery" accept="image/*" class="hidden">

                    <div class="border-2 border-dashed border-gray-300 rounded-lg h-48 flex items-center justify-center text-gray-500 bg-gray-50">
                        <img id="imageAfterPreview" class="hidden w-full h-full object-contain rounded-lg">
                        <div id="uploadPlaceholderAfter" class="text-center">
                            <div class="text-4xl mb-2">📷</div>
                            <div>No Image</div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Solution / Countermeasure
                        <span id="solutionRequired" class="text-red-500 hidden">*</span>
                    </label>
                    <textarea id="solution" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                        placeholder="Proposed solution..."></textarea>
                </div>

                <div class="pt-4 flex justify-between gap-2">
                    <button type="button" id="moveBtn"
                        class="hidden px-4 py-2 text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200 flex items-center gap-2">
                        <i data-lucide="move" class="w-4 h-4"></i>
                        Move Position
                    </button>
                    <div class="flex gap-2 ml-auto">
                        <button type="button" id="cancelBtn"
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">Cancel</button>
                        <button type="submit"
                            class="px-4 py-2 text-white bg-red-600 rounded-md hover:bg-red-700 flex items-center gap-2">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            Save Tag
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>




    <!-- Password & Upload Modal -->
    <div id="passwordModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[10000] hidden">
        <div class="bg-white rounded-lg shadow-xl p-6 w-80">
            <!-- Step 1: Password -->
            <div id="pwStep">
                <h3 class="text-lg font-bold mb-4">Enter Password</h3>
                <div class="mb-4">
                    <input type="password" id="passwordInput" class="w-full px-3 py-2 border border-gray-300 rounded"
                        placeholder="****">
                </div>
                <div class="flex justify-end gap-2">
                    <button id="cancelPwBtn" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</button>
                    <button id="verifyPwBtn"
                        class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Submit</button>
                </div>
            </div>

            <!-- Step 2: Upload -->
            <div id="uploadStep" class="hidden">
                <h3 class="text-lg font-bold mb-4">Select Floor Plan</h3>
                <div class="mb-4">
                    <input type="file" id="modalMapUpload" class="w-full text-sm text-gray-500
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-full file:border-0
                        file:text-sm file:font-semibold
                        file:bg-red-50 file:text-red-700
                        hover:file:bg-red-100" accept="image/*">
                </div>
                <div class="flex justify-end gap-2">
                    <button id="cancelUploadBtn" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</button>
                    <button id="finalUploadBtn"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Upload</button>
                </div>
            </div>
        </div>
    </div>

    <!-- User Guide Modal -->
    <div id="userGuideModal" style="z-index: 10002;"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-[10001] hidden p-4">
        <div class="bg-white rounded-lg shadow-xl w-1/2 max-h-[90vh] overflow-y-auto flex flex-col">
            <div class="flex justify-between items-center p-4 border-b">
                <h2 class="text-xl font-bold text-gray-800">User Guide</h2>
                <button id="closeUserGuideBtn" class="text-gray-500 hover:text-gray-700">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="p-6 space-y-6 overflow-y-auto">
                <!-- Content -->
                <section>
                    <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <i data-lucide="user" class="w-5 h-5"></i> General User
                    </h3>
                    <p class="text-sm text-gray-500 italic mb-3">No password required. For daily reporting and updating.
                    </p>

                    <div class="space-y-3 pl-2">
                        <div class="space-y-1">
                            <h4 class="font-semibold text-gray-700">1. Add a Red Tag ➕</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 pl-2">
                                <li>Click map location -> Fill details -> Save.</li>
                            </ul>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-semibold text-gray-700">2. Update / Close a Tag ✏️</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 pl-2">
                                <li>Click pin -> Update details -> Save.</li>
                                <li><strong>To Close:</strong> Status "Closed" requires Solution & Photo (After).</li>
                            </ul>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-semibold text-gray-700">3. Move a Tag 📍</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 pl-2">
                                <li>Open tag -> Click "Move" -> Click new location.</li>
                            </ul>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-semibold text-gray-700">4. Filter &amp; Export 📊</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 pl-2">
                                <li>Filter by <strong>Status</strong> and/or <strong>Zone</strong> dropdowns. Export to
                                    CSV.</li>
                            </ul>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-semibold text-gray-700">5. Toggle Sidebar ☰</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 pl-2">
                                <li>กดปุ่ม <strong>☰</strong> มุมซ้ายบนของแผนที่ เพื่อซ่อน/แสดง sidebar</li>
                                <li>บน tablet แนวตั้ง sidebar จะพับอัตโนมัติเพื่อเพิ่มพื้นที่แผนที่</li>
                                <li>ปุ่ม <strong>+/−</strong> ย่อ-ขยาย อยู่มุมขวาบนของแผนที่</li>
                            </ul>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-semibold text-gray-700">6. Access Dashboard 📊</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 pl-2">
                                <li>Click the <strong>Dashboard</strong> link in the top right corner of the left
                                    sidebar to view statistics.</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <hr>

                <section>
                    <h3 class="text-lg font-bold text-red-700 flex items-center gap-2">
                        <i data-lucide="shield" class="w-5 h-5"></i> Admin
                    </h3>
                    <p class="text-sm text-red-500 italic mb-3">Requires Password. For management and cleanup.</p>

                    <div class="space-y-3 pl-2">
                        <div class="space-y-1">
                            <h4 class="font-semibold text-gray-700">1. Delete a Tag 🗑️</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 pl-2">
                                <li>Click Trash icon -> Enter Password.</li>
                            </ul>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-semibold text-gray-700">2. Change Floor Layout 🗺️</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 pl-2">
                                <li>Click "Change Floor Layout" -> Enter Password -> Upload image.</li>
                            </ul>
                        </div>
                    </div>
                </section>
            </div>
            <div class="p-4 border-t bg-gray-50 flex justify-end">
                <button id="closeUserGuideBottomBtn"
                    class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Close</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <!-- Leaflet JS -->
    <script src="libs/leaflet/leaflet.js" defer></script>

    <!-- App Logic -->
    <script src="app.js?v=upload002" defer></script>
    <script defer>
        window.addEventListener('load', function () {
            if (typeof window.APP_VERSION === 'undefined' || window.APP_VERSION !== '2.0.0') {
                alert("CRITICAL UPDATE: Your browser is using an old version of the application code.\n\nPlease press Ctrl+F5 (Windows) or Cmd+Shift+R (Mac) to refresh the page and clear the cache.\n\nIf this message persists, please use Incognito/Private mode.");
            }
        });
    </script>

    <!-- Sidebar Toggle Logic -->
    <script>
        (function () {
            var sidebar = document.getElementById('sidebar');
            var btn = document.getElementById('toggleSidebarBtn');

            function isPortrait() {
                return window.innerWidth < 1024 && window.innerHeight > window.innerWidth;
            }

            function setSidebar(collapse) {
                if (collapse) {
                    sidebar.classList.add('collapsed');
                    btn.title = 'Show Sidebar';
                } else {
                    sidebar.classList.remove('collapsed');
                    btn.title = 'Hide Sidebar';
                }
                // Invalidate Leaflet map size after CSS transition (300ms)
                setTimeout(function () {
                    if (window.mapInstance) window.mapInstance.invalidateSize();
                }, 320);
            }

            // Auto-collapse on portrait tablet on first load
            if (isPortrait()) {
                setSidebar(true);
            }

            btn.addEventListener('click', function () {
                setSidebar(!sidebar.classList.contains('collapsed'));
            });

            // Re-evaluate on rotate / resize
            var lastPortrait = isPortrait();
            window.addEventListener('resize', function () {
                var nowPortrait = isPortrait();
                if (nowPortrait !== lastPortrait) {
                    // Only auto-collapse when switching TO portrait
                    // Don't force open when going to landscape (respect user choice)
                    if (nowPortrait) setSidebar(true);
                    lastPortrait = nowPortrait;
                }
            });
        })();
    </script>
</div>    
</body>

</html>