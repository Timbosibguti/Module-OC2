<?php echo isset($header) ? $header : ''; ?>
<?php echo isset($column_left) ? $column_left : ''; ?>

<div id="content">
  <ul class="nav nav-tabs">
    <li class="active"><a href="#export" data-toggle="tab">Экспорт</a></li>
    <li><a href="#import" data-toggle="tab">Импорт</a></li>
  </ul>
  <div class="tab-content">
    <div class="tab-pane active" id="export">
      <div class="page-header">
        <div class="container-fluid">
          <div class="pull-right">
            
          </div>
          <h1><?php echo $heading_title; ?></h1>
          <ul class="breadcrumb">
            <?php foreach ($breadcrumbs as $breadcrumb) { ?>
            <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
            <?php } ?>
          </ul>
        </div>
      </div>
      <div class="container-fluid">
        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-pencil"></i> Экспорт товаров</h3>
          </div>
          <div class="panel-body">
            <form id="form-export" action="<?php echo $export_action; ?>" method="post">
              <div class="form-group">
                <label class="col-sm-2 control-label">Категории</label>
                <div class="col-sm-10">
                  <div class="well well-sm" style="height: 150px; overflow-y: scroll;">
                    <?php foreach ($categories as $category) { ?>
                    <div class="checkbox">
                      <label>
                        <input type="checkbox" name="category[]" value="<?php echo $category['category_id']; ?>" /> <?php echo $category['name']; ?>
                      </label>
                    </div>
                    <?php } ?>
                  </div>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label">Атрибуты</label>
                <div class="col-sm-10">
                  <div class="well well-sm" style="height: 150px; overflow-y: scroll;">
                    <?php foreach ($attributes as $attribute) { ?>
                    <div class="checkbox">
                      <label>
                        <input type="checkbox" name="attribute[]" value="<?php echo $attribute['attribute_id']; ?>" /> <?php echo $attribute['name']; ?>
                      </label>
                    </div>
                    <?php } ?>
                  </div>
                </div>
              </div>
              <button type="submit" id="button-export" data-loading-text="Loading..." class="btn btn-primary"><i class="fa fa-download"></i> Экспорт</button>
            </form>
          </div>
        </div>
      </div>
    </div>

<div class="tab-pane" id="import">
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <input type="hidden" name="token" value="<?php echo $token; ?>" />
      </div>
      <h1>Импорт товаров</h1>
    </div>
  </div>
  <div class="container-fluid">
    <div class="row">
      <div class="col-md-6">
        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-cloud-upload"></i> Загрузка файла Excel</h3>
          </div>
          <div class="panel-body">
            <form enctype="multipart/form-data" action="<?php echo $import_action; ?>" method="post" id="form-import" class="form-horizontal">
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-file">Выберите файл:</label>
                <div class="col-sm-9">
                  <input type="file" name="import_file" id="input-file" class="form-control" />
                  <span class="help-block">Выберите файл Excel для импорта товаров.</span>
                </div>
              </div>
              <div class="form-group">
                <div class="col-sm-offset-3 col-sm-9">
                  <button id="button-import" class="btn btn-primary" disabled><i class="fa fa-upload"></i> Импорт</button>
                </div>
              </div>
              <div class="form-group" id="import-report">
                <div class="col-sm-offset-3 col-sm-9">
                  
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-database"></i> Управление бэкапом</h3>
          </div>
          <div class="panel-body">
            <button type="button" id="button-create-backup" class="btn btn-primary"><i class="fa fa-download"></i> Создать бэкап</button>
            <button type="button" id="button-restore-backup" class="btn btn-primary" disabled><i class="fa fa-upload"></i> Восстановить бэкап</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

  </div>
</div>

<style type="text/css">
    div#import-report {
        padding-left: 3%;
    }
</style>

<script type="text/javascript">
$(document).ready(function() {

    $('.nav-tabs a').click(function() {
        $(this).tab('show');
    });

    $('#form-import').submit(function(event) {
        event.preventDefault();

        var fileInput = $('input[name="import_file"]')[0];

            if (!fileInput || fileInput.files.length === 0) {
                $('#button-import').prop('disabled', true); 
                return false;
            }

        var formData = new FormData($(this)[0]);
        var token = $('input[name="token"]').val();
        formData.append('token', token); 

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            async: true,
            cache: false,
            contentType: false,
            dataType: 'json',
            processData: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = evt.loaded / evt.total * 100;
                        console.log(percentComplete);
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    $('#import-report').html('<div class="success">' + response.success + '</div>');
                } else if (response.error) {
                    $('#import-report').html('<div class="error">' + response.error + '</div>');
                }
                if (response.warning) {
                    $('#import-report').append('<div class="warning">' + response.warning + '</div>');
                }
                var newProductsMessage = 'Добавлено продуктов: ' + (response.new_products ? response.new_products : 0);
                var updatedProductsMessage = 'Обновлено продуктов: ' + (response.updated_products ? response.updated_products : 0);
                $('#import-report').append('<div class="info">' + newProductsMessage + '</div>');
                $('#import-report').append('<div class="info">' + updatedProductsMessage + '</div>');
                $('#button-import').prop('disabled', true); 
            },
            error: function(xhr, status, error) {
                alert('Произошла ошибка при загрузке файла!');
            }
        });
        return false;
    });


    $('#button-create-backup').click(function(event) {
        var token = $('input[name="token"]').val();
        event.preventDefault();
        $.ajax({
            url: 'index.php?route=tool/paexport/createBackup',
            type: 'GET',
            data: { token: token },
            dataType: 'json', 
            success: function(response) {
                if (response.success) {
                    alert(response.message); 
                    $('#button-create-backup').prop('disabled', true);
                    $('#button-restore-backup').prop('disabled', false);
                    $('#button-import').prop('disabled', false);
                    var tablesCreated = response.tablesCreated;
                } else {
                    alert('Произошла ошибка при создании бэкапа!');
                }
            },
            error: function() {
                alert('Произошла ошибка при выполнении запроса!');
            }
        });
    });


    // Обработчик кнопки восстановления бэкапа
    $('#button-restore-backup').click(function() {
        var token = $('input[name="token"]').val();
        $.ajax({
            url: 'index.php?route=tool/paexport/restoreBackup&token=' + encodeURIComponent(token),
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    alert('Бэкап успешно восстановлен!');
                } else {
                    alert('Произошла ошибка при восстановлении бэкапа!');
                }
                $('#button-create-backup').prop('disabled', false);
                $('#button-restore-backup').prop('disabled', true);
            },
            error: function() {
                alert('Произошла ошибка при выполнении AJAX-запроса!');
            }
        });
    });

});
</script>

<?php echo isset($footer) ? $footer : ''; ?>
