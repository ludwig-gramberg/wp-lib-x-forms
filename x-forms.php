<?php
/**
 * this would be less verbose to use as a class,
 * so i will add a class interface eventually
 */

define('X_FORM_DEV', false);

// misc/helper

function x_form_helper_xml($str) {
    return str_replace(array('&','<','>'),array('&amp;','&lt;','&gt;'),$str);
}

function x_form_is_submit($form) {
    return strtolower($_SERVER['REQUEST_METHOD']) == 'post' && $_POST['is_form_'.$form] == 1;
}

function x_form_forward($targetUrl) {
    return '<script type="text/javascript">document.location='.json_encode($targetUrl).';</script>';
}

function x_form_render_is_submit($form) {
    return '<input type="hidden" name="is_form_'.$form.'" value="1" />';
}

// sanitization

function x_form_sanitize_phone($value) {
    $value = trim($value);
    $value = preg_replace('/[^0-9\-\+]+/', '-', $value);
    $value = trim($value, '-');
    $value = preg_replace('/^(00|\+)/', '%', $value);
    $value = preg_replace('/[\+]+/', '-', $value);
    $value = str_replace('%', '+', $value);
    $value = trim($value, '-');
    return $value;
}

function x_form_sanitize_digits($value) {
    return preg_replace('/[^0-9]+/', '', $value);
}

function x_form_sanitize_email_tag($value) {
    $emailAddress   = strtolower(trim($value));
    $parts          = explode('@', $emailAddress);
    $recipient      = trim($parts[0]);
    $domain         = trim($parts[1]);
    $recipient      = preg_replace('/\+[^\+]+$/', '', $recipient);
    return $recipient && $domain ? $recipient.'@'.$domain : '';
}

// validation

function x_form_validate_email($value) {
    if(preg_match('/\+[^\+@]+@/', $value)) {
        return 'contains-tag';
    }
    return preg_match(
        "/^[a-z0-9\.\!#\$%&'\*\+\/\=\?\^_`\{\|\}~\-]*[a-z0-9\!#\$%&'\*\+\/\=\?\^_`\{\|\}~\-]+@([a-z0-9\-]+\.)+([a-z][a-z]+)\$/i",
        $value
    ) ? null : 'not-email';
}

function x_form_validate_phone_or_empty($value) {
    return preg_match('/^(\+|0)[1-9]([\- \/]?[0-9]+)+/', $value) || $value == '' ? null : 'not-phone-nor-empty';
}

function x_form_validate_not_empty($value) {
    return (string)$value == '' ? 'is-empty' : null;
}

function x_form_validate_true($value) {
    return $value ? null : 'not-checked';
}

// decorators

function x_form_decorate_3digits($value) {
    return preg_replace('/([0-9]) ([0-9])$/', '$1$2', trim(chunk_split($value, 3, ' ')));
}

// fields

function x_form_render_exception(Exception $e) {
    return '<div style="border-radius:5px;border:1px solid #f00;color:#f00;background-color:#edc1b6;padding:10px;font-size:12px;line-height:16px;white-space:pre;display:inline-block"><strong>Exception</strong>: '.(X_FORM_DEV ? $e : $e->getMessage()).'</div>';
}

function x_form_render_radio($form, $name, array &$read, $option, $options, $additionalHtml = '', array &$write = null, $default = null) {
    try {
        $html = '';

        if(!in_array($default, $options)) {
            $default = $options[0];
        }

        if(x_form_is_submit($form)) {
            $value = $read[$name];

            if(!in_array($value, $options)) {
                $value = $default;
            }

            // write result back to array if valid
            if(is_array($write)) {
                $write[$name] = $value;
            }

        } else {
            $value = $default;
        }

        $html .= '<input type="radio" name="'.x_form_helper_xml($name).'" ';
        $html .= 'value="'.x_form_helper_xml($option).'" ';
        if($value == $option) {
            $html .= 'checked="checked" ';
        }
        $html .= $additionalHtml;
        $html .= '>'."\n";

        return $html;

    } catch(Exception $e) {
        return x_form_render_exception($e);
    }
}

function x_form_render_checkbox($form, $name, array &$read, $required, $additionalHtml = '', array &$write = null, array &$errors = null, $default = false, $validate = null) {

    $validateCallback = function($value, &$validated) use ($validate) {
        $validated = false;
        foreach((array)$validate as $validator) {
            $validated = true;
            if(is_callable($validator)) {
                $result = $validator($value);
            } elseif(is_string($validator) && function_exists('x_form_validate_'.$validator)) {
                $result = call_user_func('x_form_validate_'.$validator, $value);
            } elseif(is_string($validator) && function_exists($validator)) {
                $result = call_user_func($validator, $value);
            } else {
                throw new Exception('unknown validator '.$validator);
            }
            if($result !== null) {
                return $result;
            }
        }
        return null;
    };

    try {
        $html = '';

        $default = (boolean)$default;
        $dataAttr = array('required' => $required ? 'true' : 'false');

        if(x_form_is_submit($form)) {

            $value = $read[$name] == 1;

            // write result back to array if valid
            if(is_array($write)) {
                $write[$name] = $value;
            }

            // validate
            $validated = false;
            if($validate !== null) {
                $result = $validateCallback($value, $validated);
                $dataAttr['validated'] = $validated ? 'true' : 'false';
                $dataAttr['valid'] = $result === null ? 'true' : 'false';
                if($result !== null) {
                    $dataAttr['error'] = $result;
                    $errors[$name] = $result;
                }
            }

        } else {
            $value = $default;

            // show resolved fields as green
            $validated = false;
            if($validate !== null) {
                $t = $validateCallback($value, $validated);
                if($validated && $t === null) {
                    $dataAttr['validated'] = 'true';
                    $dataAttr['valid'] = 'true';
                }
            }
        }

        $html .= '<input type="checkbox" name="'.x_form_helper_xml($name).'" value="1" ';
        $html .= $additionalHtml.' ';
        if($value == true) {
            $html .= 'checked="checked" ';
        }
        foreach($dataAttr as $k => $v) {
            $html .= 'data-'.$k.'="'.x_form_helper_xml($v).'" ';
        }
        $html .= '/>';

        return $html;

    } catch(Exception $e) {
        return x_form_render_exception($e);
    }
}

function x_form_render_select($form, $name, array &$read, $options, $groups, $additionalHtml = '', array &$write = null, $default = null) {
    try {
        $html = '';

        if(!array_key_exists($default, $options)) {
            reset($options);
            $default = key($options);
        }

        if(x_form_is_submit($form)) {
            $value = $read[$name];

            if(!array_key_exists($value, $options)) {
                $value = $default;
            }

            // write result back to array if valid
            if(is_array($write)) {
                $write[$name] = $value;
            }

        } else {
            $value = $default;
        }

        $html .= '<select name="'.x_form_helper_xml($name).'" ';
        $html .= $additionalHtml.' ';
        $html .= '>'."\n";

        if(!empty($groups) && is_array($groups)) {
            foreach($groups as $group_label => $group_keys) {
                $html .= '<optgroup label="'.x_form_helper_xml($group_label).'">'."\n";
                foreach($group_keys as $v) {
                    $html .= '<option value="'.x_form_helper_xml($v).'"'.($value == $v ? ' selected="selected"' : '').'>'.x_form_helper_xml($options[$v]).'</option>'."\n";
                }
                $html .= '</optgroup>'."\n";
            }
        } else {
            foreach($options as $v => $t) {
                $html .= '<option value="'.x_form_helper_xml($v).'"'.($value == $v ? ' selected="selected"' : '').'>'.x_form_helper_xml($t).'</option>'."\n";
            }
        }

        $html .= '</select>';

        return $html;

    } catch(Exception $e) {
        return x_form_render_exception($e);
    }
}

function x_form_render_hidden($form, $name, array &$read, $additionalHtml, array &$write = null, $default = '', array $allowedValues = null) {
    try {
        $html = '';

        $dataAttr = array();

        if(x_form_is_submit($form)) {

            $value = $read[$name];
            if($allowedValues !== null && !in_array($value, $allowedValues)) {
                $value = $allowedValues[0];
            }
            if(is_array($write)) {
                $write[$name] = $value;
            }

        } else {
            $value = $default;
            if($allowedValues !== null && !in_array($value, $allowedValues)) {
                $value = $allowedValues[0];
            }
        }

        $html .= '<input type="hidden" name="'.x_form_helper_xml($name).'" ';

        $html .= 'value="'.x_form_helper_xml($value).'" ';
        foreach($dataAttr as $k => $v) {
            $html .= 'data-'.$k.'="'.x_form_helper_xml($v).'" ';
        }
        $html .= $additionalHtml;
        $html .= '/>';

        return $html;

    } catch(Exception $e) {
        return x_form_render_exception($e);
    }
}

function x_form_render_text($form, $name, array &$read, $required, $additionalHtml = '', array &$write = null, array &$errors = null, $default = '', $hint = null, $sanitize = null, $validate = null, $decorator = null, $type = 'text') {

    $sanitizeCallback = function($value) use ($sanitize) { // sanitize
        foreach((array)$sanitize as $sanitizer) {
            if(is_callable($sanitizer)) {
                $value = $sanitizer($value);
            } elseif(is_string($sanitizer) && function_exists('x_form_sanitize_'.$sanitizer)) {
                $value = call_user_func('x_form_sanitize_'.$sanitizer, $value);
            } elseif(is_string($sanitizer) && function_exists($sanitizer)) {
                $value = call_user_func($sanitizer, $value);
            } else {
                throw new Exception('unknown sanitizer '.$sanitizer);
            }
        }
        return $value;
    };

    $validateCallback = function($value, &$validated) use ($validate) {
        $validated = false;
        foreach((array)$validate as $validator) {
            $validated = true;
            if(is_callable($validator)) {
                $result = $validator($value);
            } elseif(is_string($validator) && function_exists('x_form_validate_'.$validator)) {
                $result = call_user_func('x_form_validate_'.$validator, $value);
            } elseif(is_string($validator) && function_exists($validator)) {
                $result = call_user_func($validator, $value);
            } else {
                throw new Exception('unknown validator '.$validator);
            }
            if($result !== null) {
                return $result;
            }
        }
        return null;
    };

    try {
        $html = '';

        $dataAttr = array('required' => $required ? 'true' : 'false');

        if(x_form_is_submit($form)) {

            $value = $read[$name];
            if($sanitize !== null) {
                $value = $sanitizeCallback($value);
            }

            // validate
            $validated = false;
            if($validate !== null) {
                $result = $validateCallback($value, $validated);
                $dataAttr['validated'] = $validated ? 'true' : 'false';
                $dataAttr['valid'] = $result === null ? 'true' : 'false';
                if($result !== null) {
                    $dataAttr['error'] = $result;
                    $errors[$name] = $result;
                }
            }

            // write result back to array if valid
            if($result === null && is_array($write)) {
                $write[$name] = $value;
            }

        } else {
            $value = $default;

            // show resolved fields as green
            $validated = false;
            $t = $value;
            if($sanitize !== null) {
                $t = $sanitizeCallback($t);
            }
            if($validate !== null) {
                $t = $validateCallback($t, $validated);
                if($validated && $t === null) {
                    $dataAttr['validated'] = 'true';
                    $dataAttr['valid'] = 'true';
                }
            }
        }

        $html .= '<input type="'.$type.'" name="'.x_form_helper_xml($name).'" ';
        if($hint !== null) {
            $html .= 'placeholder="'.x_form_helper_xml($hint).'" ';
            $html .= 'title="'.x_form_helper_xml($hint).'" ';
        }

        if($decorator !== null) {
            if(is_callable($decorator)) {
                $value = $decorator($value);
            } elseif(is_string($decorator) && function_exists('x_form_decorate_'.$decorator)) {
                $value = call_user_func('x_form_decorate_'.$decorator, $value);
            } elseif(is_string($decorator) && function_exists($decorator)) {
                $value = call_user_func($decorator, $value);
            } else {
                throw new Exception('unknown decorator '.$decorator);
            }
        }

        $html .= 'value="'.x_form_helper_xml($value).'" ';
        foreach($dataAttr as $k => $v) {
            $html .= 'data-'.$k.'="'.x_form_helper_xml($v).'" ';
        }
        $html .= $additionalHtml;
        $html .= '/>';

        return $html;

    } catch(Exception $e) {
        return x_form_render_exception($e);
    }
}

function x_form_render_textarea($form, $name, array &$read, $required, $additionalHtml = '', array &$write = null, array &$errors = null, $default = '', $sanitize = null, $validate = null) {
    try {
        $html = '';

        $dataAttr = array('required' => $required ? 'true' : 'false');

        if(x_form_is_submit($form)) {

            $value = $read[$name];

            // sanitize
            foreach((array)$sanitize as $sanitizer) {
                if(is_callable($sanitizer)) {
                    $value = $sanitizer($value);
                } elseif(is_string($sanitizer) && function_exists('x_form_sanitize_'.$sanitizer)) {
                    $value = call_user_func('x_form_sanitize_'.$sanitizer, $value);
                } elseif(is_string($sanitizer) && function_exists($sanitizer)) {
                    $value = call_user_func($sanitizer, $value);
                } else {
                    throw new Exception('unknown sanitizer '.$sanitizer);
                }
            }


            // validate
            $result = null;
            $valid	= true;
            foreach((array)$validate as $validator) {

                $dataAttr['validated'] = 'true';
                $dataAttr['valid'] = 'true';

                if(is_callable($validator)) {
                    $result = $validator($value);
                } elseif(is_string($validator) && function_exists('x_form_validate_'.$validator)) {
                    $result = call_user_func('x_form_validate_'.$validator, $value);
                } elseif(is_string($validator) && function_exists($validator)) {
                    $result = call_user_func($validator, $value);
                } else {
                    throw new Exception('unknown validator '.$validator);
                }
                if($result !== null) {
                    $valid = false;
                    $dataAttr['valid'] = 'false';
                    $dataAttr['error'] = $result;
                    if(is_array($errors)) {
                        $errors[$name] = $result;
                    }
                    break;
                }
            }

            // write result back to array if valid
            if($valid && is_array($write)) {
                $write[$name] = $value;
            }

        } else {
            $value = $default;
        }

        $html .= '<textarea name="'.x_form_helper_xml($name).'" ';
        foreach($dataAttr as $k => $v) {
            $html .= 'data-'.$k.'="'.x_form_helper_xml($v).'" ';
        }
        $html .= $additionalHtml;
        $html .= '>';
        $html .= x_form_helper_xml($value);
        $html .= '</textarea>';

        return $html;

    } catch(Exception $e) {
        return x_form_render_exception($e);
    }
}