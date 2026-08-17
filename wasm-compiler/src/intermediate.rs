use regex::Regex;

pub fn generar(codigo: &str) -> String {
    let mut output = String::from("--- GENERACIÓN DE CÓDIGO INTERMEDIO (3 Direcciones) ---\n\n");
    let mut contador_temp = 1;
    let mut _lineas_procesadas = 0;

    let re_simple = Regex::new(r"^([\$a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*([\$a-zA-Z0-9_]+)\s*([\+\-\*\/\^%])\s*([\$a-zA-Z0-9_]+);?").unwrap();
    let re_asign_directa = Regex::new(r"^([\$a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*([\$a-zA-Z0-9_]+);?$").unwrap();
    let re_inc = Regex::new(r"^([a-zA-Z_][a-zA-Z0-9_]*)\s*(\+\+|--);?").unwrap();
    let re_return = Regex::new(r"^return\s+([\$a-zA-Z0-9_]+);?").unwrap();
    let re_cin = Regex::new(r"^cin\s*(?:>>)?\s*([a-zA-Z_][a-zA-Z0-9_]*);?").unwrap();
    let re_cout = Regex::new(r"^cout\s*(?:<<)?\s*(.+);?").unwrap();

    for (num, linea) in codigo.lines().enumerate() {
        let num_linea = num + 1;
        let linea_trim = linea.trim();

        if let Some(captures) = re_simple.captures(linea_trim) {
            let destino = captures.get(1).unwrap().as_str();
            let arg1 = captures.get(2).unwrap().as_str();
            let op = captures.get(3).unwrap().as_str();
            let arg2 = captures.get(4).unwrap().as_str();

            let temp = format!("t{}", contador_temp);
            contador_temp += 1;

            output.push_str(&format!("; Traducción de la línea {}\n", num_linea));
            output.push_str(&format!("{} = {} {} {}\n", temp, arg1, op, arg2));
            output.push_str(&format!("{} = {}\n\n", destino, temp));
            _lineas_procesadas += 1;
            continue;
        }

        if let Some(captures) = re_asign_directa.captures(linea_trim) {
            let destino = captures.get(1).unwrap().as_str();
            if !["if", "while", "return"].contains(&destino) {
                let valor = captures.get(2).unwrap().as_str();
                output.push_str(&format!("; Traducción de la línea {}\n", num_linea));
                output.push_str(&format!("{} = {}\n\n", destino, valor));
                _lineas_procesadas += 1;
                continue;
            }
        }

        if let Some(captures) = re_inc.captures(linea_trim) {
            let var_name = captures.get(1).unwrap().as_str();
            let op_str = captures.get(2).unwrap().as_str();
            let op = if op_str == "++" { "+" } else { "-" };

            let temp = format!("t{}", contador_temp);
            contador_temp += 1;

            output.push_str(&format!("; Traducción de la línea {}\n", num_linea));
            output.push_str(&format!("{} = {} {} 1\n", temp, var_name, op));
            output.push_str(&format!("{} = {}\n\n", var_name, temp));
            _lineas_procesadas += 1;
            continue;
        }

        if let Some(captures) = re_return.captures(linea_trim) {
            let ret_val = captures.get(1).unwrap().as_str();
            output.push_str(&format!("; Traducción de la línea {}\n", num_linea));
            output.push_str(&format!("ret {}\n\n", ret_val));
            _lineas_procesadas += 1;
            continue;
        }

        if let Some(captures) = re_cin.captures(linea_trim) {
            let var_name = captures.get(1).unwrap().as_str();
            output.push_str(&format!("; Traducción de la línea {}\n", num_linea));
            output.push_str(&format!("READ {}\n\n", var_name));
            _lineas_procesadas += 1;
            continue;
        }

        if let Some(captures) = re_cout.captures(linea_trim) {
            let val = captures.get(1).unwrap().as_str().replace("<<", "").replace("  ", " ");
            output.push_str(&format!("; Traducción de la línea {}\n", num_linea));
            output.push_str(&format!("WRITE {}\n\n", val.trim()));
            _lineas_procesadas += 1;
            continue;
        }
    }

    output.push_str("Generación finalizada.\n");
    output
}
