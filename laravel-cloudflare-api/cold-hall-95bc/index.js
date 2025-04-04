export default {
	async fetch(request, env) {
	  // CORS headers for cross-origin requests
	  const corsHeaders = {
		"Access-Control-Allow-Origin": "*",
		"Access-Control-Allow-Methods": "GET, POST, PUT, DELETE, OPTIONS",
		"Access-Control-Allow-Headers": "Content-Type, Authorization",
		"Content-Type": "application/json"
	  };

	  // Handle OPTIONS requests (preflight)
	  if (request.method === "OPTIONS") {
		return new Response(null, {
		  headers: corsHeaders
		});
	  }

	  // Parse the URL to get the path
	  const url = new URL(request.url);
	  const path = url.pathname.split('/').filter(part => part);

	  try {
		// Route requests based on path
		if (path[0] === 'api') {
		  if (path[1] === 'users') {
			if (request.method === "GET") {
			  if (path[2]) {
				// Get a specific user
				return await getUserById(env.DB, path[2], corsHeaders);
			  } else {
				// Get all users
				return await getAllUsers(env.DB, corsHeaders);
			  }
			} else if (request.method === "POST") {
			  // Create a new user
			  const data = await request.json();
			  return await createUser(env.DB, data, corsHeaders);
			} else if (request.method === "PUT" && path[2]) {
			  // Update a user
			  const data = await request.json();
			  return await updateUser(env.DB, path[2], data, corsHeaders);
			} else if (request.method === "DELETE" && path[2]) {
			  // Delete a user
			  return await deleteUser(env.DB, path[2], corsHeaders);
			}
		  }

		  // You can add more resource endpoints here (e.g., posts, comments, etc.)
		}

		// If no route matches, return 404
		return new Response(JSON.stringify({ error: "Not Found" }), {
		  status: 404,
		  headers: corsHeaders
		});
	  } catch (error) {
		// Handle errors
		return new Response(JSON.stringify({ error: error.message }), {
		  status: 500,
		  headers: corsHeaders
		});
	  }
	}
  };

  // Database operation functions
  async function getAllUsers(db, headers) {
	const { results } = await db.prepare("SELECT * FROM users").all();

	return new Response(JSON.stringify({ data: results }), {
	  headers: headers
	});
  }

  async function getUserById(db, id, headers) {
	const { results } = await db.prepare("SELECT * FROM users WHERE id = ?").bind(id).all();

	if (results.length === 0) {
	  return new Response(JSON.stringify({ error: "User not found" }), {
		status: 404,
		headers: headers
	  });
	}

	return new Response(JSON.stringify({ data: results[0] }), {
	  headers: headers
	});
  }

  async function createUser(db, data, headers) {
	// Validate required fields
	if (!data.name || !data.email) {
	  return new Response(JSON.stringify({ error: "Name and email are required" }), {
		status: 400,
		headers: headers
	  });
	}

	try {
	  const result = await db.prepare(
		"INSERT INTO users (name, email, created_at, updated_at) VALUES (?, ?, datetime('now'), datetime('now'))"
	  ).bind(data.name, data.email).run();

	  return new Response(JSON.stringify({
		success: true,
		id: result.meta.last_row_id
	  }), {
		status: 201,
		headers: headers
	  });
	} catch (error) {
	  return new Response(JSON.stringify({ error: error.message }), {
		status: 500,
		headers: headers
	  });
	}
  }

  async function updateUser(db, id, data, headers) {
	// Validate the data object has at least one field to update
	if (Object.keys(data).length === 0) {
	  return new Response(JSON.stringify({ error: "No fields to update" }), {
		status: 400,
		headers: headers
	  });
	}

	// Build update query dynamically based on provided fields
	let updateFields = [];
	let values = [];

	if (data.name) {
	  updateFields.push("name = ?");
	  values.push(data.name);
	}

	if (data.email) {
	  updateFields.push("email = ?");
	  values.push(data.email);
	}

	// Always update the updated_at timestamp
	updateFields.push("updated_at = datetime('now')");

	// Add the id for the WHERE clause
	values.push(id);

	const query = `UPDATE users SET ${updateFields.join(", ")} WHERE id = ?`;

	try {
	  const result = await db.prepare(query).bind(...values).run();

	  if (result.meta.changes === 0) {
		return new Response(JSON.stringify({ error: "User not found" }), {
		  status: 404,
		  headers: headers
		});
	  }

	  return new Response(JSON.stringify({
		success: true,
		changes: result.meta.changes
	  }), {
		headers: headers
	  });
	} catch (error) {
	  return new Response(JSON.stringify({ error: error.message }), {
		status: 500,
		headers: headers
	  });
	}
  }

  async function deleteUser(db, id, headers) {
	try {
	  const result = await db.prepare("DELETE FROM users WHERE id = ?").bind(id).run();

	  if (result.meta.changes === 0) {
		return new Response(JSON.stringify({ error: "User not found" }), {
		  status: 404,
		  headers: headers
		});
	  }

	  return new Response(JSON.stringify({
		success: true,
		changes: result.meta.changes
	  }), {
		headers: headers
	  });
	} catch (error) {
	  return new Response(JSON.stringify({ error: error.message }), {
		status: 500,
		headers: headers
	  });
	}
  }
