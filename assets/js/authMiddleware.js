function authorize(roles = []) {
  // roles param can be a single role string (e.g., "admin") or an array
  if (typeof roles === "string") {
    roles = [roles];
  }
  return [
    (req, res, next) => {
      if (!roles.includes(req.user.role)) {
        return res.status(403).json({ message: "Forbidden" });
      }
      next();
    },
  ];
}
module.exports = authorize;