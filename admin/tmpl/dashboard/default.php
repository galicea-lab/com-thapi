<?php
// admin/tmpl/dashboard/default.php
?>
<?php defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;

$app = Factory::getApplication();
$token = Session::getFormToken();
?>

<div class="thapi-dashboard">
    <div class="row-fluid">
        <div class="span12">
            <h1>ThApi Dashboard</h1>
        </div>
    </div>

    <div class="row-fluid">
        <div class="span3">
            <div class="card text-center">
                <h3><?php echo $this->stats['total_items'] ?? 0; ?></h3>
                <p>Total Items</p>
            </div>
        </div>
        <div class="span3">
            <div class="card text-center">
                <h3><?php echo $this->stats['active_items'] ?? 0; ?></h3>
                <p>Active Items</p>
            </div>
        </div>
        <div class="span3">
            <div class="card text-center">
                <h3><?php echo $this->stats['last_updated'] ?? 'Never'; ?></h3>
                <p>Last Updated</p>
            </div>
        </div>
        <div class="span3">
            <div class="card text-center">
                <h3>API Test</h3>
                <p>
                    <button class="btn btn-primary" onclick="testAPI()">Test API</button>
                    <button class="btn btn-success" onclick="refreshStats()">Refresh Stats</button>
                </p>
            </div>
        </div>
    </div>

    <div class="row-fluid mt-4">
        <div class="span6">
            <div class="card">
                <h3>System Information</h3>
                <ul>
                    <li>Joomla: <?php echo $this->stats['system_info']['joomla_version'] ?? 'Unknown'; ?></li>
                    <li>PHP: <?php echo $this->stats['system_info']['php_version'] ?? 'Unknown'; ?></li>
                    <li>Database: <?php echo $this->stats['system_info']['database_version'] ?? 'Unknown'; ?></li>
                </ul>
            </div>
        </div>
        <div class="span6">
            <div class="card">
                <h3>Quick Actions</h3>
                <div class="btn-group-vertical">
                    <a href="<?php echo Route::_('index.php?option=com_thapi&view=configuration'); ?>" class="btn btn-info">
                        <span class="icon-cog"></span> Configuration
                    </a>
                    <button class="btn btn-success" onclick="thapiAPI.refreshData()">
                        <span class="icon-refresh"></span> Refresh Data
                    </button>
                    <a href="<?php echo Route::_('index.php?option=com_config&view=component&component=com_thapi'); ?>" class="btn btn-warning">
                        <span class="icon-options"></span> Component Settings
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Sekcja JSON Export Management -->
<!--
    <div class="row-fluid mt-4">
        <div class="span12">
            <div class="card">
                <h3>JSON Export Management</h3>
                
                <div class="row-fluid">
                    <div class="span6">
                        <h4>Available Categories</h4>
                        <div id="categories-list" style="max-height: 300px; overflow-y: auto; margin-bottom: 15px;">
                            <div class="alert alert-info">Loading categories...</div>
                        </div>
                        
                        <button class="btn btn-success" onclick="loadCategories()">
                            <span class="icon-refresh"></span> Reload Categories
                        </button>
                    </div>
                    
                    <div class="span6">
                        <h4>API Endpoints</h4>
                        <div class="well">
                            <strong>JSON Export:</strong><br>
                            <code id="export-url"><?php echo $this->getExportUrl(); ?></code>
                            <button class="btn btn-small" onclick="copyToClipboard('export-url')">Copy</button>
                            <br><br>
                            
                            <strong>Check Updates:</strong><br>
                            <code id="updates-url"><?php echo $this->getUpdatesUrl(); ?></code>
                            <button class="btn btn-small" onclick="copyToClipboard('updates-url')">Copy</button>
                            <br><br>
                            
                            <strong>Get Categories:</strong><br>
                            <code id="categories-url"><?php echo $this->getCategoriesUrl(); ?></code>
                            <button class="btn btn-small" onclick="copyToClipboard('categories-url')">Copy</button>
                            <br><br>
                            
                            <strong>Test Endpoints:</strong>
                            <button class="btn btn-info btn-sm" onclick="testEndpoint('exportJson')">Test Export</button>
                            <button class="btn btn-info btn-sm" onclick="testEndpoint('checkUpdates')">Check Updates</button>
                            <button class="btn btn-info btn-sm" onclick="testEndpoint('getCategories')">Get Categories</button>
                        </div>
                    </div>
                </div>
                
                <div id="api-test-result" style="margin-top: 10px;"></div>
            </div>
        </div>
    </div>
-->

</div>

<script>
const token = '<?php echo $token; ?>';

function testAPI() {
    const apiUrl = `index.php?option=com_thapi&task=dashboard.getStats&format=json&${token}=1`;
    
    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            alert('API Response: ' + JSON.stringify(data));
        })
        .catch(error => {
            alert('API Error: ' + error.message);
        });
}

function testEndpoint(endpoint) {
    const apiUrl = `index.php?option=com_thapi&task=api.${endpoint}&format=json&${token}=1`;
    
    document.getElementById('api-test-result').innerHTML = '<div class="alert alert-info">Testing... Please wait</div>';
    
    fetch(apiUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            document.getElementById('api-test-result').innerHTML = 
                '<div class="alert alert-success"><strong>Success:</strong> ' + endpoint + '</div>' +
                '<pre style="background: #f5f5f5; padding: 10px; border-radius: 5px; max-height: 300px; overflow: auto; font-size: 12px;">' +
                JSON.stringify(data, null, 2) +
                '</pre>';
        })
        .catch(error => {
            document.getElementById('api-test-result').innerHTML = 
                '<div class="alert alert-error"><strong>Error:</strong> ' + error.message + '</div>';
        });
}

function loadCategories() {
    const apiUrl = `https://argumenty.net/index.php?option=com_thapi&task=api.getCategories\'; //&${token}=?;
    
    document.getElementById('categories-list').innerHTML = '<div class="alert alert-info">Loading categories...</div>';
    
    fetch(apiUrl)
        .then(response => response.json())
        .then(categories => {
            let html = '<table class="table table-striped table-condensed">';
            html += '<thead><tr><th>ID</th><th>Title</th><th>Description</th></tr></thead><tbody>';
            
            if (categories.length === 0) {
                html += '<tr><td colspan="3" class="text-center">No categories found</td></tr>';
            } else {
                categories.forEach(cat => {
                    html += `<tr>
                        <td><strong>${cat.id}</strong></td>
                        <td>${cat.title}</td>
                        <td><small>${cat.description || 'No description'}</small></td>
                    </tr>`;
                });
            }
            
            html += '</tbody></table>';
            document.getElementById('categories-list').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('categories-list').innerHTML = 
                '<div class="alert alert-error">Error loading categories: ' + error.message + '</div>';
        });
}

function refreshStats() {
    location.reload();
}

function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    const text = element.textContent;
    
    navigator.clipboard.writeText(text).then(function() {
        alert('URL copied to clipboard!');
    }, function(err) {
        console.error('Could not copy text: ', err);
        // Fallback dla starszych przeglądarek
        const textArea = document.createElement("textarea");
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand("copy");
        document.body.removeChild(textArea);
        alert('URL copied to clipboard!');
    });
}

// Załaduj kategorie przy starcie
document.addEventListener('DOMContentLoaded', function() {
    loadCategories();
});

var thapiAPI = {
    getStats: function() {
        fetch('index.php?option=com_thapi&task=dashboard.getStats&format=json&' + token + '=1')
            .then(response => response.json())
            .then(data => {
                alert('Stats: ' + JSON.stringify(data));
            });
    },
    
    refreshData: function() {
        location.reload();
    }
};
</script>

<style>
.thapi-dashboard .card {
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
.thapi-dashboard .card h3 {
    margin-top: 0;
    color: #2a69b8;
}
.btn-group-vertical .btn {
    margin-bottom: 5px;
    text-align: left;
}
.well {
    background: #f5f5f5;
    padding: 15px;
    border-radius: 5px;
    border: 1px solid #ddd;
}
.table-condensed th,
.table-condensed td {
    padding: 4px 8px;
    font-size: 12px;
}
</style>