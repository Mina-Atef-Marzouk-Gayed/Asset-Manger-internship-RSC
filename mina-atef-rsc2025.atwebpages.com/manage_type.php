<?php include 'db.php'; $id=(int)($_GET['id']??0); if(!$id) { header('Location: asset_types.php'); exit; }
$type = $conn->query("SELECT * FROM assettypes WHERE TypeID=$id")->fetch_assoc();

// Get sort parameters
$sort_column = $_GET['sort'] ?? 'AttributeID';
$sort_order = $_GET['order'] ?? 'ASC';

// Validate sort column
$allowed_columns = ['AttributeID', 'Name', 'DataType', 'is_required'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'AttributeID';
}

// Validate sort order
$sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

// Build ORDER BY clause
$order_by = '';
switch($sort_column) {
    case 'AttributeID':
        $order_by = "AttributeID $sort_order";
        break;
    case 'Name':
        $order_by = "Name $sort_order";
        break;
    case 'DataType':
        $order_by = "DataType $sort_order";
        break;
    case 'is_required':
        $order_by = "is_required $sort_order";
        break;
    default:
        $order_by = "AttributeID ASC";
}

$attrs = $conn->query("SELECT * FROM assetattributes WHERE TypeID=$id ORDER BY $order_by")->fetch_all(MYSQLI_ASSOC);

// Function to generate sort URL
function getSortUrl($column, $current_sort, $current_order, $type_id) {
    $new_order = ($current_sort === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
    return "?id=$type_id&sort=$column&order=$new_order";
}

// Function to get sort icon
function getSortIcon($column, $current_sort, $current_order) {
    if ($current_sort === $column) {
        return $current_order === 'ASC' ? '↑' : '↓';
    }
    return '↕';
}
?>
<!doctype html><html><head><meta charset='utf-8'><title>Manage Type</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.sortable { cursor: pointer; }
.sortable:hover { background-color: #f8f9fa; }
.sort-icon { margin-left: 5px; color: #6c757d; }
</style>
</head>
<body class="container py-4">
<a class="btn btn-sm btn-secondary mb-3" href="asset_types.php">Back</a>
<h2>Manage: <?=htmlspecialchars($type['TypeName'])?></h2>
<div class="card mb-3 p-3">
  <h5>Add Attribute</h5>
  <form id="attrForm" class="row g-2">
    <div class="col-md-5"><input name="attr_name" class="form-control" placeholder="Attribute name e.g. RAM" required></div>
    <div class="col-md-3"><select name="data_type" class="form-select"><option>Text</option><option>Number</option><option>Date</option><option>Boolean</option></select></div>
    <div class="col-md-2"><div class="form-check"><input type="checkbox" name="is_required" value="1" class="form-check-input" id="req"><label class="form-check-label" for="req">Required</label></div></div>
    <div class="col-md-2"><button class="btn btn-primary">Add</button></div>
  </form>
</div>
<div id="msg"></div>
<h5>Attributes</h5>
<table class="table table-sm" id="attrTable">
<thead>
<tr>
    <th class="sortable" onclick="window.location.href='<?= getSortUrl('AttributeID', $sort_column, $sort_order, $id) ?>'">
        # <span class="sort-icon"><?= getSortIcon('AttributeID', $sort_column, $sort_order) ?></span>
    </th>
    <th class="sortable" onclick="window.location.href='<?= getSortUrl('Name', $sort_column, $sort_order, $id) ?>'">
        Name <span class="sort-icon"><?= getSortIcon('Name', $sort_column, $sort_order) ?></span>
    </th>
    <th class="sortable" onclick="window.location.href='<?= getSortUrl('DataType', $sort_column, $sort_order, $id) ?>'">
        Type <span class="sort-icon"><?= getSortIcon('DataType', $sort_column, $sort_order) ?></span>
    </th>
    <th class="sortable" onclick="window.location.href='<?= getSortUrl('is_required', $sort_column, $sort_order, $id) ?>'">
        Req <span class="sort-icon"><?= getSortIcon('is_required', $sort_column, $sort_order) ?></span>
    </th>
    <th>Action</th>
</tr>
</thead>
<tbody>
  <?php foreach($attrs as $i=>$a): ?>
    <tr data-id="<?= $a['AttributeID'] ?>" data-name="<?= htmlspecialchars($a['Name']) ?>" data-type="<?= htmlspecialchars($a['DataType']) ?>" data-required="<?= (int)$a['is_required'] ?>">
      <td><?= $i+1 ?></td>
      <td><?= htmlspecialchars($a['Name']) ?></td>
      <td><?= htmlspecialchars($a['DataType']) ?></td>
      <td><?= $a['is_required']?'Yes':'No' ?></td>
      <td>
        <button class="btn btn-sm btn-secondary editAttr">Edit</button>
        <button class="btn btn-sm btn-danger delAttr">Delete</button>
      </td>
    </tr>
  <?php endforeach; ?>
</tbody></table>

<!-- Edit Attribute Modal -->
<div class="modal fade" id="editAttrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Attribute</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editAttrForm">
        <div class="modal-body">
          <input type="hidden" name="attribute_id" id="edit_attribute_id">
          <input type="hidden" name="type_id" value="<?= (int)$id ?>">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="attr_name" id="edit_attr_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Type</label>
            <select name="data_type" id="edit_data_type" class="form-select">
              <option value="Text">Text</option>
              <option value="Number">Number</option>
              <option value="Date">Date</option>
              <option value="Boolean">Boolean</option>
            </select>
          </div>
          <div class="form-check">
            <input type="checkbox" name="is_required" value="1" id="edit_is_required" class="form-check-input">
            <label class="form-check-label" for="edit_is_required">Required</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
 </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const typeId = <?= (int)$id ?>;
const form = document.getElementById('attrForm');
const tableBody = document.querySelector('#attrTable tbody');
const msg = document.getElementById('msg');

function renderRows(list){
  tableBody.innerHTML = '';
  list.forEach((a, idx) => {
    const tr = document.createElement('tr');
    tr.setAttribute('data-id', a.AttributeID);
    tr.setAttribute('data-name', a.Name||'');
    tr.setAttribute('data-type', a.DataType||'Text');
    tr.setAttribute('data-required', String(a.is_required==1?1:0));
    tr.innerHTML = `<td>${idx+1}</td><td>${escapeHtml(a.Name)}</td><td>${escapeHtml(a.DataType)}</td><td>${a.is_required==1?'Yes':'No'}</td>
                    <td><button class="btn btn-sm btn-secondary editAttr">Edit</button> <button class=\"btn btn-sm btn-danger delAttr\">Delete</button></td>`;
    tableBody.appendChild(tr);
  });
}

function escapeHtml(s){
  return s==null?'' : String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m]));
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(form);
  fd.append('action','add');
  fd.append('type_id', String(typeId));
  try{
    const res = await fetch('manage_type_actions.php', { method:'POST', body: fd });
    const data = await res.json();
    if(data.ok){
      renderRows(data.attributes);
      form.reset();
      msg.innerHTML = '<div class="alert alert-success">Attribute added</div>';
    } else {
      msg.innerHTML = '<div class="alert alert-danger">'+escapeHtml(data.error||'Failed')+'</div>';
    }
  }catch(err){
    msg.innerHTML = '<div class="alert alert-danger">Request failed</div>';
  }
});

// delegate delete clicks
document.addEventListener('click', async (e) => {
  if(e.target && e.target.classList.contains('delAttr')){
    const tr = e.target.closest('tr');
    const attrId = tr.getAttribute('data-id');
    if(!confirm('Delete attribute?')) return;
    const fd = new FormData();
    fd.append('action','delete');
    fd.append('type_id', String(typeId));
    fd.append('attribute_id', String(attrId));
    try{
      const res = await fetch('manage_type_actions.php', { method:'POST', body: fd });
      const data = await res.json();
      if(data.ok){
        renderRows(data.attributes);
        msg.innerHTML = '<div class="alert alert-info">Attribute deleted</div>';
      } else {
        msg.innerHTML = '<div class="alert alert-danger">'+escapeHtml(data.error||'Failed')+'</div>';
      }
    }catch(err){
      msg.innerHTML = '<div class="alert alert-danger">Request failed</div>';
    }
  }
  if(e.target && e.target.classList.contains('editAttr')){
    const tr = e.target.closest('tr');
    const attrId = tr.getAttribute('data-id');
    const name = tr.getAttribute('data-name') || '';
    const type = tr.getAttribute('data-type') || 'Text';
    const required = tr.getAttribute('data-required') === '1';

    document.getElementById('edit_attribute_id').value = attrId;
    document.getElementById('edit_attr_name').value = name;
    document.getElementById('edit_data_type').value = type;
    document.getElementById('edit_is_required').checked = required;

    const modal = new bootstrap.Modal(document.getElementById('editAttrModal'));
    modal.show();
  }
});

document.getElementById('editAttrForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action','update');
  try{
    const res = await fetch('manage_type_actions.php', { method:'POST', body: fd });
    const data = await res.json();
    if(data.ok){
      renderRows(data.attributes);
      msg.innerHTML = '<div class="alert alert-success">Attribute updated</div>';
      bootstrap.Modal.getInstance(document.getElementById('editAttrModal')).hide();
    } else {
      msg.innerHTML = '<div class="alert alert-danger">'+escapeHtml(data.error||'Failed')+'</div>';
    }
  }catch(err){
    msg.innerHTML = '<div class="alert alert-danger">Request failed</div>';
  }
});
</script>
</body></html>
