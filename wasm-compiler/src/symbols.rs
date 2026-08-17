use serde::{Serialize, Deserialize};
use std::collections::{HashMap, BTreeMap};
use regex::Regex;

#[derive(Serialize, Deserialize, Debug)]
pub struct SymbolInfo {
    pub identificador: String,
    pub tipo: String,
    pub lineas: String,
}

#[derive(Serialize, Deserialize, Debug)]
pub struct SymbolsResult {
    pub success: bool,
    pub simbolos: Vec<SymbolInfo>,
}

pub fn generar(codigo: &str) -> SymbolsResult {
    let mut simbolos: BTreeMap<String, (String, Vec<usize>)> = BTreeMap::new();

    let re_class = Regex::new(r"class\s+([a-zA-Z_][a-zA-Z0-9_]*)").unwrap();
    let re_func = Regex::new(r"(?:function|int|float|void|real|bool)\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(").unwrap();
    let re_main_standalone = Regex::new(r"\bmain\b").unwrap();
    let re_vars = Regex::new(r"(?:int|float|double|char|string|real|bool)\s+([a-zA-Z_][a-zA-Z0-9_,\s]*)").unwrap();
    let re_assign = Regex::new(r"([a-zA-Z_][a-zA-Z0-9_]*)\s*(:=|=)").unwrap();

    for (num, linea) in codigo.lines().enumerate() {
        let num_linea = num + 1;

        // 1. Detección de Clases
        for captures in re_class.captures_iter(linea) {
            let id = captures.get(1).unwrap().as_str().to_string();
            let entry = simbolos.entry(id).or_insert_with(|| ("Clase".to_string(), Vec::new()));
            if !entry.1.contains(&num_linea) { entry.1.push(num_linea); }
        }

        // 2. Detección de Funciones
        for captures in re_func.captures_iter(linea) {
            let func = captures.get(1).unwrap().as_str().to_string();
            if !["if", "while", "switch", "for", "main"].contains(&func.as_str()) {
                let entry = simbolos.entry(func).or_insert_with(|| ("Función/Método".to_string(), Vec::new()));
                if !entry.1.contains(&num_linea) { entry.1.push(num_linea); }
            } else if func == "main" {
                let entry = simbolos.entry(func).or_insert_with(|| ("Punto de Entrada".to_string(), Vec::new()));
                if !entry.1.contains(&num_linea) { entry.1.push(num_linea); }
            }
        }

        if re_main_standalone.is_match(linea) && !simbolos.contains_key("main") {
            let entry = simbolos.entry("main".to_string()).or_insert_with(|| ("Punto de Entrada".to_string(), Vec::new()));
            if !entry.1.contains(&num_linea) { entry.1.push(num_linea); }
        }

        // 3. Declaración de Variables
        for captures in re_vars.captures_iter(linea) {
            let vars_str = captures.get(1).unwrap().as_str();
            for v in vars_str.split(',') {
                let var_limpia = v.trim().replace(|c: char| !c.is_ascii_alphanumeric() && c != '_', "");
                if !var_limpia.is_empty() && var_limpia != "main" {
                    let entry = simbolos.entry(var_limpia).or_insert_with(|| ("Variable".to_string(), Vec::new()));
                    if !entry.1.contains(&num_linea) { entry.1.push(num_linea); }
                }
            }
        }

        // 4. Asignaciones directas o de uso
        for captures in re_assign.captures_iter(linea) {
            let var_name = captures.get(1).unwrap().as_str().to_string();
            if !["let", "var", "const", "return", "if", "while", "for", "else", "do"].contains(&var_name.as_str()) {
                let entry = simbolos.entry(var_name).or_insert_with(|| ("Asignación/Uso".to_string(), Vec::new()));
                if !entry.1.contains(&num_linea) { entry.1.push(num_linea); }
            }
        }
    }

    let mut output = Vec::new();
    for (id, (tipo, lineas)) in simbolos {
        let lineas_str = lineas.iter().map(|l| l.to_string()).collect::<Vec<String>>().join(", ");
        output.push(SymbolInfo {
            identificador: id,
            tipo,
            lineas: lineas_str,
        });
    }

    SymbolsResult {
        success: true,
        simbolos: output,
    }
}
