<?php
class ControllerToolPaexport extends Controller {
	private $import_action;
    public function index() {
        $this->document->setTitle('Экспорт/импорт товаров');

        $data['heading_title'] = 'Экспорт/импорт товаров';

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => 'Главная',
            'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL')
        );

        $data['breadcrumbs'][] = array(
            'text' => 'Экспорт/импорт товаров',
            'href' => $this->url->link('tool/paexport', 'token=' . $this->session->data['token'], 'SSL')
        );

        // Получение списка категорий
        $this->load->model('catalog/category');
        $categories = $this->model_catalog_category->getCategories(array());

        $data['categories'] = $categories;

        // Получение списка атрибутов
        $this->load->model('catalog/attribute');
        $attributes = $this->model_catalog_attribute->getAttributes(array());

        $data['attributes'] = $attributes;

        // Действие для отправки формы экспорта
        $data['export_action'] = $this->url->link('tool/paexport/export', 'token=' . $this->session->data['token'], 'SSL');
        $data['import_action'] = $this->url->link('tool/paexport/import', 'token=' . $this->session->data['token'], 'SSL');
        $data['token'] = $this->session->data['token'];


        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->load->language('tool/paexport');
	    $this->document->setTitle($this->language->get('heading_title'));
	    
	    // Проверяем, был ли выполнен импорт и получаем его результаты
	    $data['import_report'] = '';
	    if ($this->session->data['import_report']) {
	        $data['import_report'] = $this->session->data['import_report'];
	        unset($this->session->data['import_report']); // Очищаем данные о результате импорта после их использования
	    }

        $this->response->setOutput($this->load->view('tool/paexport.tpl', $data));
    }

	public function export() {
	    require_once(DIR_SYSTEM . "library/PHPExcel.php");

	    // получаем категории и атрибуты
	    $selected_categories = isset($this->request->post['category']) ? $this->request->post['category'] : array();
	    $selected_attributes = isset($this->request->post['attribute']) ? $this->request->post['attribute'] : array();

	    // Получаем товаров в выбранных категориях и их дочерних категориях
	    $this->load->model('catalog/product');
	    $products = $this->model_catalog_product->getProducts(array('filter_status' => 1, 'filter_category_id' => $selected_categories));

	    // Сортируем товары по названию
	    usort($products, function($a, $b) {
	        return strcmp($a['name'], $b['name']);
	    });

	    // Получаем названий атрибутов
	    $this->load->model('catalog/attribute');
	    $attributes = $this->model_catalog_attribute->getAttributes(array());
	    $attribute_names = array();
	    foreach ($attributes as $attribute) {
	        if (in_array($attribute['attribute_id'], $selected_attributes)) {
	            $attribute_names[$attribute['attribute_id']] = $attribute['name'];
	        }
	    }

	    $objPHPExcel = new PHPExcel();
	    $objPHPExcel->getActiveSheet()->freezePane('A2');

	    $objPHPExcel->setActiveSheetIndex(0)
	                ->setCellValue('A1', 'ID')
	                ->setCellValue('B1', 'Название')
	                ->setCellValue('C1', 'Артикул');

	    $objPHPExcel->getActiveSheet()->freezePane('D1');
		$objPHPExcel->getActiveSheet()->freezePane('E1');
		$objPHPExcel->getActiveSheet()->freezePane('F1');

	    // Добавляем столбцы для выбранных атрибутов
	    $column_index = 3;
	    foreach ($attribute_names as $attribute_name) {
	        $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column_index++, 1, $attribute_name);
	    }

	    // Начинаем считать строки с 2-ой, так как первая строка занята заголовками
	    $row = 2;

	    foreach ($products as $product) {
	        // Получаем ID, название и артикул товара
	        $product_id = $product['product_id'];
	        $name = $product['name'];
	        $sku = $product['sku'];

	        $product_attributes = $this->model_catalog_product->getProductAttributes($product_id);

	        $product_attribute_values = array_fill(0, count($attribute_names), '');

	        // Заполняем значения атрибутов товара
	        foreach ($product_attributes as $attribute) {
	            if (in_array($attribute['attribute_id'], $selected_attributes)) {
	                $index = array_search($attribute['attribute_id'], array_keys($attribute_names));
	                $product_attribute_values[$index] = isset($attribute['product_attribute_description'][1]['text']) ? $attribute['product_attribute_description'][1]['text'] : '';
	            }
	        }

	        $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, $product_id)
	                                      ->setCellValue('B' . $row, $name)
	                                      ->setCellValue('C' . $row, $sku);

	        $column_index = 3;
	        foreach ($product_attribute_values as $attribute_value) {
	            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column_index++, $row, $attribute_value);
	        }

	        $row++;
	    }

	    $filename = 'export_' . date('Y-m-d_H-i-s') . '.xlsx';

	    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	    header('Content-Disposition: attachment;filename="' . $filename . '"');
	    header('Cache-Control: max-age=0');

	    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');

	    $objWriter->save('php://output');
	    exit;
	}

	public function import() {
	    require_once(DIR_SYSTEM . "library/PHPExcel.php");
	    $response = array();

	    if (isset($this->request->post['token']) && isset($this->request->files['import_file']['tmp_name'])) {
	        $token = $this->request->post['token'];
	        $inputFileName = $this->request->files['import_file']['tmp_name'];

	        $objPHPExcel = PHPExcel_IOFactory::load($inputFileName);
	        $sheet = $objPHPExcel->getActiveSheet();

	        $header = array();
	        $attribute_ids = array();
	        $error_messages = array();
	        $updated_products_count = 0;
	        $new_products_count = 0;

	        foreach ($sheet->getRowIterator(1, 1) as $row) {
	            $cellIterator = $row->getCellIterator();
	            $cellIterator->setIterateOnlyExistingCells(false);
	            foreach ($cellIterator as $cell) {
	                $header[] = $cell->getValue();
	            }
	        }

	        $this->load->model('catalog/product');

	        // Проверка наличия атрибутов и дублирования атрибутов
	        $attribute_names_check = array();
			for ($i = 3; $i < count($header); $i++) {
			    $attribute_name = $header[$i];
			    if (isset($attribute_names_check[$attribute_name])) {
			        // Если атрибут дублируется, добавляем сообщение об этом только если оно еще не было добавлено
			        if (!isset($error_messages['duplicate'])) {
			            $error_messages['duplicate'] = "Дублирующие атрибуты: " . $attribute_name;
			        }
			    } else {
			        $attribute_names_check[$attribute_name] = true;
			        $query = $this->db->query("SELECT attribute_id FROM " . DB_PREFIX . "attribute_description WHERE name = '" . $this->db->escape($attribute_name) . "'");
			        if ($query->num_rows) {
			            $attribute_ids[$i] = $query->row['attribute_id'];
			        } else {
			            // Если атрибут не найден, добавляем сообщение об этом только если оно еще не было добавлено
			            if (!isset($error_messages['not_found'])) {
			                $error_messages['not_found'] = "Атрибуты не найденные: " . $attribute_name;
			            }
			        }
			    }
			}

	        foreach ($sheet->getRowIterator(2) as $row) {
	            $cellIterator = $row->getCellIterator();
	            $cellIterator->setIterateOnlyExistingCells(false);

	            $product_data = array();
	            $attributes = array();
	            $column = 0;
	            foreach ($cellIterator as $cell) {
	                $value = $cell->getValue();
	                if ($column == 0) {
	                    $product_data['product_id'] = $value;
	                } elseif ($column == 1) {
	                    $product_data['name'] = $value;
	                } elseif ($column == 2) {
	                    $product_data['sku'] = $value;
	                } elseif (isset($attribute_ids[$column])) {
	                    $attributes[$attribute_ids[$column]] = $value;
	                }
	                $column++;
	            }

	            $product_data = array_merge(array(
	                'model' => '',
	                'quantity' => 0,
	                'stock_status_id' => 0,
	                'image' => '',
	                'manufacturer_id' => 0,
	                'shipping' => 1,
	                'price' => 0.00,
	                'points' => 0,
	                'tax_class_id' => 0,
	                'date_available' => date('Y-m-d'),
	                'weight' => 0.00,
	                'weight_class_id' => 0,
	                'length' => 0.00,
	                'width' => 0.00,
	                'height' => 0.00,
	                'length_class_id' => 0,
	                'subtract' => 1,
	                'minimum' => 1,
	                'sort_order' => 1,
	                'status' => 1,
	                'keyword' => '',
	                'product_description' => array(
	                    1 => array(
	                        'name' => $product_data['name'],
	                        'description' => '',
	                        'meta_title' => $product_data['name'],
	                        'meta_description' => '',
	                        'meta_keyword' => ''
	                    )
	                ),
	                'product_store' => array(0)
	            ), $product_data);

	            if (isset($product_data['product_id'])) {
	                // Обновляем продукт
	                $this->model_catalog_product->editProduct($product_data['product_id'], $product_data);

	                // Получаем существующие атрибуты продукта
	                $existing_attributes = $this->model_catalog_product->getProductAttributes($product_data['product_id']);
	                $existing_attribute_map = array();
	                foreach ($existing_attributes as $attribute) {
	                    $existing_attribute_map[$attribute['attribute_id']] = $attribute;
	                }

	                // Обновляем или добавляем атрибуты
	                foreach ($attributes as $attribute_id => $text) {
	                    if (isset($existing_attribute_map[$attribute_id])) {
	                        // Обновляем существующий атрибут
	                        $this->db->query("UPDATE " . DB_PREFIX . "product_attribute SET text = '" . $this->db->escape($text) . "' WHERE product_id = '" . (int)$product_data['product_id'] . "' AND attribute_id = '" . (int)$attribute_id . "' AND language_id = 1");
	                    } else {
	                        // Добавляем новый атрибут
	                        $this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET product_id = '" . (int)$product_data['product_id'] . "', attribute_id = '" . (int)$attribute_id . "', language_id = 1, text = '" . $this->db->escape($text) . "'");
	                    }
	                }
	            } else {
	                // Добавляем новый продукт
	                $this->model_catalog_product->addProduct($product_data);
	                $product_id = $this->db->getLastId();
	                foreach ($attributes as $attribute_id => $text) {
	                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET product_id = '" . (int)$product_id . "', attribute_id = '" . (int)$attribute_id . "', language_id = 1, text = '" . $this->db->escape($text) . "'");
	                }
	            }
	        }

	        $response['success'] = 'Импорт данных завершен успешно!';
	        if (!empty($error_messages)) {
	            $response['warning'] = implode("<br>", $error_messages);
	        }
	        $response['updated_products'] = $updated_products_count;
	        $response['new_products'] = $new_products_count;
	    } else {
	        $response['error'] = 'Ошибка: Не удалось загрузить файл или отсутствует токен!';
	    }

	    echo json_encode($response);
	}





	public function createBackup() {
	    $this->createBackupTables();
	    $this->copyDataToBackupTables(); 

	    $success_message = 'Бэкап успешно создан!';
	    $error_message = 'Ошибка при создании бэкапа!';
	    $tables_created = $this->tablesCreated(); 

	    if (!empty($tables_created)) {
	        $response = ['success' => true, 'message' => $success_message, 'tablesCreated' => $tables_created];
	    } else {
	        $response = ['success' => false, 'message' => $error_message];
	    }

	    $this->response->addHeader('Content-Type: application/json');
	    $this->response->setOutput(json_encode($response));
	}

	public function createBackupTables() {
	    try {
	        // Создание таблиц
	        $this->db->query("
	            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "product_bexp` (
	                `product_id` int(11) NOT NULL,
	                `model` varchar(64) NOT NULL,
	                PRIMARY KEY (`product_id`)
	            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
	        ");

	        $this->db->query("
	            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "product_attribute_bexp` (
	                `product_id` int(11) NOT NULL,
	                `attribute_id` int(11) NOT NULL,
	                `text` text NOT NULL,
	                PRIMARY KEY (`product_id`, `attribute_id`)
	            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
	        ");
	        return true;
	    } catch (Exception $e) {
	        return false;
	    }
	}

	public function copyDataToBackupTables() {
	    try {
	        // Копирование данных
	        $this->db->query("
	            INSERT INTO `" . DB_PREFIX . "product_bexp` (product_id, model)
	            SELECT product_id, model FROM `" . DB_PREFIX . "product`;
	        ");

	        $this->db->query("
	            INSERT INTO `" . DB_PREFIX . "product_attribute_bexp` (product_id, attribute_id, text)
	            SELECT product_id, attribute_id, '' AS text FROM `" . DB_PREFIX . "product_attribute`;
	        ");
	        return true;
	    } catch (Exception $e) {
	        return false;
	    }
	}

	public function tablesCreated() {
	    // Проверка, созданы ли таблицы
	    $tables = array();

	    $query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "product_bexp'");
	    if ($query->num_rows) {
	        $tables[] = DB_PREFIX . 'product_bexp';
	    }

	    $query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "product_attribute_bexp'");
	    if ($query->num_rows) {
	        $tables[] = DB_PREFIX . 'product_attribute_bexp';
	    }

	    return $tables;
	}

	public function restoreBackup() {
	    // Восстанавливаем данные из резервных таблиц
	    $this->restoreDataFromBackupTables();

	    // Получаем количество записей в резервных таблицах
	    $num_products = $this->getNumProductsFromBackupTables();
	    $num_attributes = $this->getNumAttributesFromBackupTables();

	    // Проверяем, были ли восстановлены таблицы
	    $success_message = 'Бэкап успешно восстановлен!';
	    $error_message = 'Ошибка при восстановлении бэкапа!';

	    if ($num_products > 0 || $num_attributes > 0) {
	        $response = ['success' => true, 'message' => $success_message, 'num_products' => $num_products, 'num_attributes' => $num_attributes];
	    } else {
	        $response = ['success' => false, 'message' => $error_message];
	    }

	    // Возвращаем ответ
	    $this->response->addHeader('Content-Type: application/json');
	    $this->response->setOutput(json_encode($response));
	}

	public function restoreDataFromBackupTables() {
	    try {
	        // Обновление данных
	        $this->db->query("
	            INSERT INTO `" . DB_PREFIX . "product` (product_id, model)
	            SELECT product_id, model FROM `" . DB_PREFIX . "product_bexp`
	            ON DUPLICATE KEY UPDATE model = VALUES(model);
	        ");

	        $this->db->query("
	            INSERT INTO `" . DB_PREFIX . "product_attribute` (product_id, attribute_id, text)
	            SELECT product_id, attribute_id, text FROM `" . DB_PREFIX . "product_attribute_bexp`
	            ON DUPLICATE KEY UPDATE text = VALUES(text);
	        ");
	        return true;
	    } catch (Exception $e) {
	        return false;
	    }
	}

	private function getNumProductsFromBackupTables() {
	    $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "product_bexp");
	    return ($query->num_rows > 0) ? $query->row['total'] : 0;
	}

	private function getNumAttributesFromBackupTables() {
	    $query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product_attribute_bexp`");
	    return ($query->num_rows > 0) ? $query->row['total'] : 0;
	}

}


?>
