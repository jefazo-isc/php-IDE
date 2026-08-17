use serde::{Serialize, Deserialize};
use regex::Regex;
use std::collections::HashMap;

#[derive(Serialize, Deserialize, Debug)]
pub struct SemanticError {
    pub linea: usize,
    pub var: String,
    pub msg: String,
}

#[derive(Serialize, Deserialize, Debug)]
pub struct SemanticResult {
    pub success: bool,
    pub output: String,
    pub errores: Vec<SemanticError>,
}

pub fn analizar(codigo: &str) -> SemanticResult {
    let mut output = String::from("--- ANÁLISIS SEMÁNTICO ---\n\n");
    let mut declaradas = HashMap::new();
    let mut errores = Vec::new();

    let re_tipo_multiple = Regex::new(r"(?:int|float|real|bool|string|let|var)\s+([a-zA-Z_][a-zA-Z0-9_,\s]*);?").unwrap();
    let re_php_var = Regex::new(r"(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*=").unwrap();
    
    // 1. Buscar declaraciones
    for (num, linea) in codigo.lines().enumerate() {
        let num_linea = num + 1;
        
        if let Some(captures) = re_tipo_multiple.captures(linea) {
            let vars_str = captures.get(1).unwrap().as_str();
            for v in vars_str.split(',') {
                let v_limpia = v.trim().replace(|c: char| !c.is_ascii_alphanumeric() && c != '_', "");
                if !v_limpia.is_empty() {
                    declaradas.insert(v_limpia.clone(), "Tipo detectado".to_string());
                    output.push_str(&format!("Validación (Línea {}): Variable '{}' declarada correctamente.\n", num_linea, v_limpia));
                }
            }
        }
        
        if let Some(captures) = re_php_var.captures(linea) {
            let var_name = captures.get(1).unwrap().as_str();
            declaradas.insert(var_name.to_string(), "Variable PHP".to_string());
            output.push_str(&format!("Validación (Línea {}): Variable '{}' inicializada en contexto.\n", num_linea, var_name));
        }
    }

    output.push_str("\n--- Verificación de Uso ---\n");

    let re_asign = Regex::new(r"^([a-zA-Z_][a-zA-Z0-9_]*)\s*=").unwrap();
    let re_cin = Regex::new(r"cin\s*(?:>>)?\s*([a-zA-Z_][a-zA-Z0-9_]*)").unwrap();

    // 2. Verificación de uso y asignaciones
    for (num, linea) in codigo.lines().enumerate() {
        let num_linea = num + 1;
        let linea_trim = linea.trim();

        if let Some(captures) = re_asign.captures(linea_trim) {
            let var_name = captures.get(1).unwrap().as_str();
            if !declaradas.contains_key(var_name) && !["echo", "return", "main"].contains(&var_name) {
                output.push_str(&format!("Error Semántico (Línea {}): La variable '{}' está siendo usada pero no fue declarada.\n", num_linea, var_name));
                errores.push(SemanticError {
                    linea: num_linea,
                    var: var_name.to_string(),
                    msg: format!("La variable '{}' está siendo usada pero no fue declarada.", var_name),
                });
            }
        }

        if let Some(captures) = re_cin.captures(linea_trim) {
            let var_name = captures.get(1).unwrap().as_str();
            if !declaradas.contains_key(var_name) && !["echo", "return"].contains(&var_name) {
                output.push_str(&format!("Error Semántico (Línea {}): La variable '{}' en 'cin' no fue declarada.\n", num_linea, var_name));
                errores.push(SemanticError {
                    linea: num_linea,
                    var: var_name.to_string(),
                    msg: format!("La variable '{}' en 'cin' no fue declarada.", var_name),
                });
            }
        }
    }

    if errores.is_empty() {
        output.push_str("\nVerificación semántica completada con éxito. No hay inconsistencias de declaración.\n");
    }

    SemanticResult {
        success: errores.is_empty(),
        output,
        errores,
    }
}
